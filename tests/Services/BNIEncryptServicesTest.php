<?php

namespace OjiePermana\Laravel\Tests\Services;

use OjiePermana\Laravel\Services\BNIEncryptServices;
use PHPUnit\Framework\TestCase;

class BNIEncryptServicesTest extends TestCase
{
    private string $client_id  = '001';
    private string $secret_key = 'ea0c88921fb033387e66ef7d1e82ab83';

    private array $sampleData = [
        'client_id'        => '001',
        'trx_amount'       => '100000',
        'customer_name'    => 'Mr. X',
        'customer_email'   => 'xxx@email.com',
        'customer_phone'   => '08123123123',
        'virtual_account'  => '8001000000000001',
        'trx_id'           => '1230000001',
        'type'             => 'createBilling',
        'datetime_expired' => '2015-07-01 16:00:00',
    ];

    // ---------------------------------------------------------------
    // Enc
    // ---------------------------------------------------------------

    /** Enc harus mengembalikan string non-empty */
    public function test_enc_returns_non_empty_string(): void
    {
        $result = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /** Enc dua kali dengan data sama menghasilkan string berbeda (karena timestamp berbeda) */
    public function test_enc_produces_different_output_each_call(): void
    {
        $first  = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);
        sleep(1);
        $second = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);

        $this->assertNotSame($first, $second);
    }

    /** Enc dengan data kosong tetap mengembalikan string */
    public function test_enc_with_empty_array(): void
    {
        $result = BNIEncryptServices::Enc([], $this->client_id, $this->secret_key);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /** Enc dengan client_id berbeda menghasilkan output berbeda */
    public function test_enc_different_client_id_produces_different_output(): void
    {
        $first  = BNIEncryptServices::Enc($this->sampleData, '001', $this->secret_key);
        $second = BNIEncryptServices::Enc($this->sampleData, '002', $this->secret_key);

        $this->assertNotSame($first, $second);
    }

    /** Enc dengan secret_key berbeda menghasilkan output berbeda */
    public function test_enc_different_secret_key_produces_different_output(): void
    {
        $first  = BNIEncryptServices::Enc($this->sampleData, $this->client_id, 'secret_a');
        $second = BNIEncryptServices::Enc($this->sampleData, $this->client_id, 'secret_b');

        $this->assertNotSame($first, $second);
    }

    // ---------------------------------------------------------------
    // Enc → Dec (roundtrip)
    // ---------------------------------------------------------------

    /** Enc lalu Dec harus mengembalikan data asli */
    public function test_enc_then_dec_returns_original_data(): void
    {
        $hashed = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, $this->secret_key);

        $this->assertIsArray($result);
        $this->assertSame($this->sampleData['trx_amount'], $result['trx_amount']);
        $this->assertSame($this->sampleData['customer_name'], $result['customer_name']);
        $this->assertSame($this->sampleData['virtual_account'], $result['virtual_account']);
        $this->assertSame($this->sampleData['trx_id'], $result['trx_id']);
        $this->assertSame($this->sampleData['customer_email'], $result['customer_email']);
        $this->assertSame($this->sampleData['customer_phone'], $result['customer_phone']);
    }

    /** Roundtrip dengan data minimal (1 key) */
    public function test_roundtrip_with_minimal_data(): void
    {
        $data   = ['virtual_account' => '8001000000000001'];
        $hashed = BNIEncryptServices::Enc($data, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, $this->secret_key);

        $this->assertSame($data, $result);
    }

    /** Roundtrip dengan data kosong */
    public function test_roundtrip_with_empty_array(): void
    {
        $hashed = BNIEncryptServices::Enc([], $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, $this->secret_key);

        $this->assertSame([], $result);
    }

    /** Roundtrip dengan nilai yang mengandung karakter spesial */
    public function test_roundtrip_with_special_characters(): void
    {
        $data = [
            'name'    => 'Budi & Siti <VIP>',
            'note'    => 'pembayaran "lunas"',
            'unicode' => 'テスト',
        ];

        $hashed = BNIEncryptServices::Enc($data, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, $this->secret_key);

        $this->assertSame($data, $result);
    }

    /** Roundtrip dengan nilai numerik */
    public function test_roundtrip_with_numeric_values(): void
    {
        $data = [
            'amount' => 50000,
            'count'  => 3,
            'rate'   => 1.5,
        ];

        $hashed = BNIEncryptServices::Enc($data, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, $this->secret_key);

        $this->assertSame($data['amount'], $result['amount']);
        $this->assertSame($data['count'], $result['count']);
        $this->assertSame($data['rate'], $result['rate']);
    }

    // ---------------------------------------------------------------
    // Dec — skenario gagal
    // ---------------------------------------------------------------

    /** Dec dengan client_id salah harus mengembalikan null */
    public function test_dec_with_wrong_client_id_returns_null(): void
    {
        $hashed = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, 'WRONG_ID', $this->secret_key);

        $this->assertNull($result);
    }

    /** Dec dengan secret_key salah harus mengembalikan null */
    public function test_dec_with_wrong_secret_key_returns_null(): void
    {
        $hashed = BNIEncryptServices::Enc($this->sampleData, $this->client_id, $this->secret_key);
        $result = BNIEncryptServices::Dec($hashed, $this->client_id, 'wrong_secret_key');

        $this->assertNull($result);
    }

    /** Dec dengan string acak harus mengembalikan null */
    public function test_dec_with_random_string_returns_null(): void
    {
        $result = BNIEncryptServices::Dec('ini-bukan-hash-valid', $this->client_id, $this->secret_key);

        $this->assertNull($result);
    }

    /** Dec dengan string kosong harus mengembalikan null */
    public function test_dec_with_empty_string_returns_null(): void
    {
        $result = BNIEncryptServices::Dec('', $this->client_id, $this->secret_key);

        $this->assertNull($result);
    }
}
