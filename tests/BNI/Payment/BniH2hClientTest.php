<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\BNI\Payment;

use Bank\BNI\Payment\BniH2hClient;
use Bank\BNI\Payment\Exceptions\BniH2hApiException;
use Bank\BNI\Payment\Exceptions\BniH2hHttpException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class BniH2hClientTest extends TestCase
{
    private const BASE_URL = 'https://bni.test';

    private const OAUTH_URL = 'https://bni.test/api/oauth/token';

    /**
     * @var array<string, mixed>
     */
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'base_url' => self::BASE_URL,
            'oauth_url' => self::OAUTH_URL,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'api_key' => 'api-key',
            'api_secret' => 'api-secret',
            'client_name' => 'MY-APP',
            'client_id_prefix' => 'IDBNI',
            'timeout_seconds' => 30,
            'verify_ssl' => true,
        ];

        config()->set('cache.default', 'array');
        Cache::flush();
    }

    // ---------------------------------------------------------------
    // OAuth Token
    // ---------------------------------------------------------------

    public function test_get_oauth_token_is_cached_and_force_refresh_fetches_new_token(): void
    {
        Http::fake([
            self::OAUTH_URL => Http::sequence()
                ->push(['access_token' => 'token-first'], 200)
                ->push(['access_token' => 'token-second'], 200),
        ]);

        $client = $this->makeClient();

        $token1 = $client->getOAuthToken();
        $token2 = $client->getOAuthToken();
        $token3 = $client->getOAuthToken(true);

        $this->assertSame('token-first', $token1);
        $this->assertSame('token-first', $token2);
        $this->assertSame('token-second', $token3);
        Http::assertSentCount(2);
    }

    // ---------------------------------------------------------------
    // Request signing & headers
    // ---------------------------------------------------------------

    public function test_do_payment_sends_signature_and_authorization_header(): void
    {
        $this->fakeOAuthToken('access-123', [
            self::BASE_URL . '/H2H/v2/dopayment' => Http::response(['responseCode' => '0001', 'status' => 'ok'], 200),
        ]);

        $client = $this->makeClient();

        $result = $client->doPayment([
            'customerReferenceNumber' => '  REF-123  ',
            'notes' => [
                'description' => '  hello world  ',
            ],
        ]);

        $this->assertSame('0001', $result['responseCode']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::BASE_URL . '/H2H/v2/dopayment') {
                return false;
            }

            $body = $request->data();

            $this->assertArrayHasKey('signature', $body);
            $this->assertNotSame('', (string) $body['signature']);
            $this->assertSame('REF-123', $body['customerReferenceNumber']);
            $this->assertSame('hello world', $body['notes']['description']);
            $this->assertSame('Bearer access-123', $request->header('Authorization')[0] ?? null);
            $this->assertSame('api-key', $request->header('x-api-key')[0] ?? null);

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Endpoint mapping
    // ---------------------------------------------------------------

    #[DataProvider('endpointProvider')]
    public function test_each_operation_hits_expected_endpoint(string $method, string $endpoint): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === self::OAUTH_URL) {
                return Http::response(['access_token' => 'access-xyz'], 200);
            }

            return Http::response(['responseCode' => '0001'], 200);
        });

    $client = $this->makeClient();

        $result = $client->{$method}(['field' => 'value']);

        $this->assertSame('0001', $result['responseCode']);

        Http::assertSent(function (Request $request) use ($endpoint): bool {
            return $request->url() === self::BASE_URL . $endpoint;
        });
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'inhouse inquiry' => ['getInhouseInquiry', '/H2H/v2/getinhouseinquiry'],
            'interbank inquiry' => ['getInterbankInquiry', '/H2H/v2/getinterbankinquiry'],
            'do payment' => ['doPayment', '/H2H/v2/dopayment'],
            'payment status' => ['getPaymentStatus', '/H2H/v2/getpaymentstatus'],
        ];
    }

    // ---------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------

    public function test_non_2xx_response_throws_http_exception(): void
    {
        $this->fakeOAuthToken('access-123', [
            self::BASE_URL . '/H2H/v2/getpaymentstatus' => Http::response(['message' => 'server error'], 500),
        ]);

        $client = $this->makeClient();

        try {
            $client->getPaymentStatus(['customerReferenceNumber' => 'REF-1']);
            $this->fail('Expected BniH2hHttpException was not thrown.');
        } catch (BniH2hHttpException $exception) {
            $this->assertSame(500, $exception->getStatusCode());
            $this->assertSame('server error', $exception->getResponseBody()['message']);
        }
    }

    public function test_non_success_response_code_throws_api_exception(): void
    {
        $this->fakeOAuthToken('access-123', [
            self::BASE_URL . '/H2H/v2/getinterbankinquiry' => Http::response(['responseCode' => '9999', 'message' => 'failed'], 200),
        ]);

        $client = $this->makeClient();

        try {
            $client->getInterbankInquiry(['customerReferenceNumber' => 'REF-1']);
            $this->fail('Expected BniH2hApiException was not thrown.');
        } catch (BniH2hApiException $exception) {
            $this->assertSame('9999', $exception->getResponseCode());
            $this->assertSame('failed', $exception->getResponseData()['message']);
        }
    }

    public function test_missing_access_token_throws_api_exception(): void
    {
        Http::fake([
            self::OAUTH_URL => Http::response(['responseCode' => '0001'], 200),
        ]);

        $client = $this->makeClient();

        $this->expectException(BniH2hApiException::class);
        $client->getOAuthToken();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeClient(): BniH2hClient
    {
        return new BniH2hClient($this->config);
    }

    /**
     * @param array<string, mixed> $additionalFakes
     */
    private function fakeOAuthToken(string $token, array $additionalFakes = []): void
    {
        Http::fake(array_merge([
            self::OAUTH_URL => Http::response(['access_token' => $token], 200),
        ], $additionalFakes));
    }
}
