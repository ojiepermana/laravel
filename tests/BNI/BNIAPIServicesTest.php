<?php

namespace OjiePermana\Laravel\Tests\BNI;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use OjiePermana\Laravel\Bank\BNI\Billing\BNIAPIServices;
use OjiePermana\Laravel\Bank\BNI\Billing\BNIEncryptServices;

class BNIAPIServicesTest extends TestCase
{
    private string $clientId  = '001';
    private string $secretKey = 'ea0c88921fb033387e66ef7d1e82ab83';
    private string $prefix    = '8';
    private string $fakeUrl   = 'https://fake.bni-ecollection.com/';

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeService(): BNIAPIServices
    {
        return new BNIAPIServices(
            clientId:  $this->clientId,
            secretKey: $this->secretKey,
            prefix:    $this->prefix,
            url:       $this->fakeUrl,
        );
    }

    /** Membuat fake response sukses BNI — data terenkripsi seperti aslinya */
    private function fakeSuccessResponse(array $data): array
    {
        return [
            'status' => '000',
            'data'   => BNIEncryptServices::Enc($data, $this->clientId, $this->secretKey),
        ];
    }

    // ---------------------------------------------------------------
    // create
    // ---------------------------------------------------------------

    /** create sukses harus mengembalikan virtual_account dan trx_id terdekripsi */
    public function test_create_success_returns_decrypted_data(): void
    {
        $expectedData = [
            'virtual_account' => '8001000000000001',
            'trx_id'          => 'INV-001',
        ];

        Http::fake(['*' => Http::response($this->fakeSuccessResponse($expectedData))]);

        $result = $this->makeService()->create(
            trxId:        'INV-001',
            trxAmount:    '100000',
            billingType:  'c',
            customerName: 'Budi Santoso',
        );

        $this->assertSame('000', $result['status']);
        $this->assertSame($expectedData['virtual_account'], $result['data']['virtual_account']);
        $this->assertSame($expectedData['trx_id'], $result['data']['trx_id']);
    }

    /** create harus mengirim payload terenkripsi dengan field yang benar ke API */
    public function test_create_sends_correct_encrypted_payload(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['virtual_account' => '8001000000000001', 'trx_id' => 'INV-001']))]);

        $this->makeService()->create(
            trxId:           'INV-001',
            trxAmount:       '100000',
            billingType:     'c',
            customerName:    'Budi Santoso',
            customerEmail:   'budi@email.com',
            customerPhone:   '08123123123',
            virtualAccount:  '8001000000000001',
            datetimeExpired: '2025-12-31T23:59:00+07:00',
            description:     'Pembayaran tagihan',
        );

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame($this->clientId, $body['client_id']);
            $this->assertSame($this->prefix, $body['prefix']);
            $this->assertArrayHasKey('data', $body);

            $decrypted = BNIEncryptServices::Dec($body['data'], $this->clientId, $this->secretKey);

            $this->assertSame('createbilling', $decrypted['type']);
            $this->assertSame('INV-001', $decrypted['trx_id']);
            $this->assertSame('100000', $decrypted['trx_amount']);
            $this->assertSame('c', $decrypted['billing_type']);
            $this->assertSame('Budi Santoso', $decrypted['customer_name']);
            $this->assertSame('budi@email.com', $decrypted['customer_email']);

            return true;
        });
    }

    /** create dengan sendSms=true harus menggunakan type createbillingsms */
    public function test_create_with_sendSms_uses_createbillingsms_type(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['virtual_account' => '8001000000000001', 'trx_id' => 'INV-002']))]);

        $this->makeService()->create(
            trxId:        'INV-002',
            trxAmount:    '50000',
            billingType:  'c',
            customerName: 'Siti Aminah',
            sendSms:      true,
        );

        Http::assertSent(function ($request) {
            $decrypted = BNIEncryptServices::Dec($request->data()['data'], $this->clientId, $this->secretKey);
            $this->assertSame('createbillingsms', $decrypted['type']);
            return true;
        });
    }

    /** create harus mengabaikan field optional yang null — tidak masuk payload terenkripsi */
    public function test_create_excludes_null_optional_fields(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['virtual_account' => '8001000000000001', 'trx_id' => 'INV-003']))]);

        $this->makeService()->create(
            trxId:        'INV-003',
            trxAmount:    '75000',
            billingType:  'o',
            customerName: 'Rini Wulandari',
            // semua optional field dibiarkan null
        );

        Http::assertSent(function ($request) {
            $decrypted = BNIEncryptServices::Dec($request->data()['data'], $this->clientId, $this->secretKey);

            $this->assertArrayNotHasKey('customer_email', $decrypted);
            $this->assertArrayNotHasKey('customer_phone', $decrypted);
            $this->assertArrayNotHasKey('virtual_account', $decrypted);
            $this->assertArrayNotHasKey('datetime_expired', $decrypted);
            $this->assertArrayNotHasKey('description', $decrypted);

            return true;
        });
    }

    // ---------------------------------------------------------------
    // update
    // ---------------------------------------------------------------

    /** update sukses harus mengembalikan virtual_account dan trx_id terdekripsi */
    public function test_update_success_returns_decrypted_data(): void
    {
        $expectedData = [
            'virtual_account' => '8001000000000001',
            'trx_id'          => 'INV-001',
        ];

        Http::fake(['*' => Http::response($this->fakeSuccessResponse($expectedData))]);

        $result = $this->makeService()->update(
            trxId:        'INV-001',
            trxAmount:    '150000',
            customerName: 'Budi Santoso',
        );

        $this->assertSame('000', $result['status']);
        $this->assertSame($expectedData['virtual_account'], $result['data']['virtual_account']);
        $this->assertSame($expectedData['trx_id'], $result['data']['trx_id']);
    }

    /** update harus mengirim type=updatebilling dan field mandatory yang benar */
    public function test_update_sends_correct_type_and_fields(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['virtual_account' => '8001000000000001', 'trx_id' => 'INV-001']))]);

        $this->makeService()->update(
            trxId:           'INV-001',
            trxAmount:       '150000',
            customerName:    'Budi Santoso',
            customerEmail:   'budi.baru@email.com',
            datetimeExpired: '2026-06-30T23:59:00+07:00',
        );

        Http::assertSent(function ($request) {
            $decrypted = BNIEncryptServices::Dec($request->data()['data'], $this->clientId, $this->secretKey);

            $this->assertSame('updatebilling', $decrypted['type']);
            $this->assertSame('INV-001', $decrypted['trx_id']);
            $this->assertSame('150000', $decrypted['trx_amount']);
            $this->assertSame('Budi Santoso', $decrypted['customer_name']);
            $this->assertSame('budi.baru@email.com', $decrypted['customer_email']);

            return true;
        });
    }

    // ---------------------------------------------------------------
    // show
    // ---------------------------------------------------------------

    /** show sukses harus mengembalikan data billing lengkap terdekripsi */
    public function test_show_success_returns_full_decrypted_data(): void
    {
        $expectedData = [
            'client_id'                    => '001',
            'trx_id'                       => 'INV-001',
            'trx_amount'                   => '100000',
            'virtual_account'              => '8001000000000001',
            'customer_name'                => 'Budi Santoso',
            'customer_email'               => 'budi@email.com',
            'customer_phone'               => '08123123123',
            'va_status'                    => '1',
            'billing_type'                 => 'c',
            'payment_amount'               => '0',
            'payment_ntb'                  => null,
            'datetime_created_iso8601'     => '2025-01-01T08:00:00+07:00',
            'datetime_expired_iso8601'     => '2025-12-31T23:59:00+07:00',
            'datetime_last_updated_iso8601'=> null,
            'datetime_payment_iso8601'     => null,
            'description'                  => 'Pembayaran tagihan',
        ];

        Http::fake(['*' => Http::response($this->fakeSuccessResponse($expectedData))]);

        $result = $this->makeService()->show('INV-001');

        $this->assertSame('000', $result['status']);
        $this->assertSame('INV-001', $result['data']['trx_id']);
        $this->assertSame('100000', $result['data']['trx_amount']);
        $this->assertSame('8001000000000001', $result['data']['virtual_account']);
        $this->assertSame('1', $result['data']['va_status']);
    }

    /** show harus mengirim type=inquirybilling dan trx_id yang benar */
    public function test_show_sends_correct_type_and_trx_id(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['trx_id' => 'INV-001']))]);

        $this->makeService()->show('INV-001');

        Http::assertSent(function ($request) {
            $decrypted = BNIEncryptServices::Dec($request->data()['data'], $this->clientId, $this->secretKey);

            $this->assertSame('inquirybilling', $decrypted['type']);
            $this->assertSame('INV-001', $decrypted['trx_id']);
            $this->assertSame($this->clientId, $decrypted['client_id']);

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Error response
    // ---------------------------------------------------------------

    /** Response error dari BNI (status != 000) harus dikembalikan apa adanya tanpa decrypt */
    public function test_api_error_response_returned_as_is(): void
    {
        $errorResponse = ['status' => '101', 'message' => 'Billing not found.'];
        Http::fake(['*' => Http::response($errorResponse)]);

        $result = $this->makeService()->show('TIDAK-ADA');

        $this->assertSame('101', $result['status']);
        $this->assertSame('Billing not found.', $result['message']);
    }

    /** Response dengan status selain 000 tidak boleh memiliki field data terdekripsi */
    public function test_error_response_has_no_decrypted_data(): void
    {
        Http::fake(['*' => Http::response(['status' => '001', 'message' => 'Incomplete/invalid Parameter(s).'])]);

        $result = $this->makeService()->create(
            trxId:        'INV-ERR',
            trxAmount:    '0',
            billingType:  'c',
            customerName: 'Test',
        );

        $this->assertArrayNotHasKey('data', $result);
        $this->assertSame('001', $result['status']);
    }

    // ---------------------------------------------------------------
    // Verifikasi HTTP request
    // ---------------------------------------------------------------

    /** Setiap request harus dikirim ke URL yang benar dengan Content-Type: application/json */
    public function test_request_sent_to_correct_url_with_json_content_type(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['trx_id' => 'INV-001', 'virtual_account' => '8001000000000001']))]);

        $this->makeService()->show('INV-001');

        Http::assertSent(function ($request) {
            $this->assertSame($this->fakeUrl, $request->url());
            $this->assertSame('application/json', $request->header('Content-Type')[0]);
            return true;
        });
    }

    /** Payload request harus selalu mengandung client_id, prefix, dan data */
    public function test_request_payload_always_contains_client_id_prefix_and_data(): void
    {
        Http::fake(['*' => Http::response($this->fakeSuccessResponse(['trx_id' => 'INV-001', 'virtual_account' => '8001000000000001']))]);

        $this->makeService()->create(
            trxId:        'INV-001',
            trxAmount:    '100000',
            billingType:  'c',
            customerName: 'Test',
        );

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertArrayHasKey('client_id', $body);
            $this->assertArrayHasKey('prefix', $body);
            $this->assertArrayHasKey('data', $body);
            $this->assertSame($this->clientId, $body['client_id']);
            $this->assertSame($this->prefix, $body['prefix']);
            $this->assertIsString($body['data']);
            $this->assertNotEmpty($body['data']);

            return true;
        });
    }
}
