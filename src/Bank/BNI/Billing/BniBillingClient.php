<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Bank\BNI\Billing;

use Illuminate\Support\Facades\Http;

class BniBillingClient
{
    /**
     * @param string $clientId  Client ID provided by BNI
     * @param string $secretKey Secret key (32 hex chars) provided by BNI
     * @param string $prefix    VA prefix number provided by BNI
     * @param string $url       BNI eCollection API URL from env BNI_BILLING_URL (config key: bni.billing.url)
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $secretKey,
        private readonly string $prefix,
        private readonly string $url = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function createBilling(
        string $trxId,
        string $trxAmount,
        string $billingType,
        string $customerName,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $virtualAccount = null,
        ?string $datetimeExpired = null,
        ?string $description = null,
        bool $sendSms = false,
    ): array {
        $payload = array_filter([
            'type' => $sendSms ? 'createbillingsms' : 'createbilling',
            'client_id' => $this->clientId,
            'trx_id' => $trxId,
            'trx_amount' => $trxAmount,
            'billing_type' => $billingType,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'virtual_account' => $virtualAccount,
            'datetime_expired' => $datetimeExpired,
            'description' => $description,
        ], fn ($value) => $value !== null);

        return $this->sendEncryptedRequest($payload);
    }

    /**
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function updateBilling(
        string $trxId,
        string $trxAmount,
        string $customerName,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $datetimeExpired = null,
        ?string $description = null,
    ): array {
        $payload = array_filter([
            'type' => 'updatebilling',
            'client_id' => $this->clientId,
            'trx_id' => $trxId,
            'trx_amount' => $trxAmount,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'datetime_expired' => $datetimeExpired,
            'description' => $description,
        ], fn ($value) => $value !== null);

        return $this->sendEncryptedRequest($payload);
    }

    /**
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function inquireBilling(string $trxId): array
    {
        $payload = [
            'type' => 'inquirybilling',
            'client_id' => $this->clientId,
            'trx_id' => $trxId,
        ];

        return $this->sendEncryptedRequest($payload);
    }

    /**
     * Backward-compatible alias of createBilling().
     *
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function create(
        string $trxId,
        string $trxAmount,
        string $billingType,
        string $customerName,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $virtualAccount = null,
        ?string $datetimeExpired = null,
        ?string $description = null,
        bool $sendSms = false,
    ): array {
        return $this->createBilling(
            trxId: $trxId,
            trxAmount: $trxAmount,
            billingType: $billingType,
            customerName: $customerName,
            customerEmail: $customerEmail,
            customerPhone: $customerPhone,
            virtualAccount: $virtualAccount,
            datetimeExpired: $datetimeExpired,
            description: $description,
            sendSms: $sendSms,
        );
    }

    /**
     * Backward-compatible alias of updateBilling().
     *
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function update(
        string $trxId,
        string $trxAmount,
        string $customerName,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $datetimeExpired = null,
        ?string $description = null,
    ): array {
        return $this->updateBilling(
            trxId: $trxId,
            trxAmount: $trxAmount,
            customerName: $customerName,
            customerEmail: $customerEmail,
            customerPhone: $customerPhone,
            datetimeExpired: $datetimeExpired,
            description: $description,
        );
    }

    /**
     * Backward-compatible alias of inquireBilling().
     *
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    public function show(string $trxId): array
    {
        return $this->inquireBilling($trxId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, data: array|null}|array{status: string, message: string}
     */
    private function sendEncryptedRequest(array $payload): array
    {
        $encrypted = BniBillingEncryptor::encryptPayload($payload, $this->clientId, $this->secretKey);

        $requestPayload = [
            'client_id' => $this->clientId,
            'prefix' => $this->prefix,
            'data' => $encrypted,
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($this->url, $requestPayload)
            ->json();

        if (($response['status'] ?? null) === '000' && isset($response['data'])) {
            return [
                'status' => '000',
                'data' => BniBillingEncryptor::decryptPayload($response['data'], $this->clientId, $this->secretKey),
            ];
        }

        return $response;
    }
}
