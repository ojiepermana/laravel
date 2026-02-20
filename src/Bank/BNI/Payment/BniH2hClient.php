<?php

declare(strict_types=1);

namespace Bank\BNI\Payment;

use Bank\BNI\Payment\Contracts\BniH2hClientContract;
use Bank\BNI\Payment\Exceptions\BniH2hApiException;
use Bank\BNI\Payment\Exceptions\BniH2hHttpException;
use Bank\BNI\Payment\Support\InputSanitizer;
use Bank\BNI\Payment\Support\JwtSignature;
use Bank\BNI\Payment\Support\ReferenceNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BniH2hClient implements BniH2hClientContract
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if ($config === []) {
            /** @var array<string, mixed> $resolvedConfig */
            $resolvedConfig = config('bni.payment', []);
            $config = $resolvedConfig;
        }

        $this->config = $config;
    }

    public function getOAuthToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh && $this->accessToken !== null && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        if ($forceRefresh) {
            Cache::forget('bni.payment.oauth_token');
        }

        /** @var string $token */
        $token = Cache::remember('bni.payment.oauth_token', now()->addSeconds($this->tokenTtl()), function (): string {
            return $this->refreshToken();
        });

        return $token;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getInhouseInquiry(array $payload): array
    {
        return $this->sendSignedRequest('/H2H/v2/getinhouseinquiry', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getInterbankInquiry(array $payload): array
    {
        return $this->sendSignedRequest('/H2H/v2/getinterbankinquiry', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function doPayment(array $payload): array
    {
        return $this->sendSignedRequest('/H2H/v2/dopayment', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getPaymentStatus(array $payload): array
    {
        return $this->sendSignedRequest('/H2H/v2/getpaymentstatus', $payload);
    }

    public function makeCustomerReferenceNumber(): string
    {
        return ReferenceNumber::generate('CRN');
    }

    /**
     * @return array<string, mixed>
     */
    private function sendSignedRequest(string $endpoint, array $payload): array
    {
        $token = $this->getOAuthToken();

        $normalized = InputSanitizer::sanitize($payload);
        $signaturePayload = $normalized;
        $signaturePayload['clientId'] = $this->buildClientId();
        $signature = JwtSignature::encode($signaturePayload, $this->configString('api_secret'));

        $requestPayload = $normalized;
        $requestPayload['signature'] = $signature;

        $response = $this->postJson($this->makeUrl($endpoint), $requestPayload, [
            'Authorization' => 'Bearer ' . $token,
            'x-api-key' => $this->configString('api_key'),
            'x-client-id' => $this->buildClientId(),
        ]);

        $this->assertApiSuccess($response);

        return $response;
    }

    private function refreshToken(): string
    {
        $response = $this->postJson($this->configString('oauth_url'), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->configString('client_id'),
            'client_secret' => $this->configString('client_secret'),
        ], [
            'x-api-key' => $this->configString('api_key'),
            'x-client-id' => $this->buildClientId(),
        ]);

        if (! isset($response['access_token']) || ! is_string($response['access_token'])) {
            throw new BniH2hApiException(
                isset($response['responseCode']) ? (string) $response['responseCode'] : 'TOKEN_MISSING',
                $response,
                'BNI OAuth token response does not include access_token'
            );
        }

        $this->accessToken = $response['access_token'];
        $this->accessTokenExpiresAt = time() + $this->tokenTtl();

        return $this->accessToken;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $payload, array $headers = []): array
    {
        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers);

        $response = Http::timeout($this->timeout())
            ->withOptions(['verify' => $this->verifySsl()])
            ->withHeaders($headers)
            ->post($url, $payload);

        if (! $response->successful()) {
            $json = $response->json();
            $data = is_array($json) ? $json : [];

            throw new BniH2hHttpException(
                $response->status(),
                $data,
                'BNI H2H request failed with HTTP status ' . $response->status()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertApiSuccess(array $response): void
    {
        $responseCode = isset($response['responseCode']) ? (string) $response['responseCode'] : null;

        if ($responseCode !== '0001') {
            throw new BniH2hApiException(
                $responseCode ?? 'UNKNOWN',
                $response,
                'BNI H2H API returned responseCode ' . ($responseCode ?? 'UNKNOWN')
            );
        }
    }

    private function buildClientId(): string
    {
        return $this->configString('client_id_prefix') . base64_encode($this->configString('client_name'));
    }

    private function tokenTtl(): int
    {
        return 55 * 60;
    }

    private function makeUrl(string $endpoint): string
    {
        return rtrim($this->configString('base_url'), '/') . '/' . ltrim($endpoint, '/');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout_seconds'] ?? 30);
    }

    private function verifySsl(): bool
    {
        return (bool) ($this->config['verify_ssl'] ?? true);
    }

    private function configString(string $key): string
    {
        return (string) ($this->config[$key] ?? '');
    }
}