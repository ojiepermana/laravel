<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OjiePermana\Laravel\Bank\BNI\Billing\BNIAPIServices;

/**
 * BNI eCollection API Facade.
 *
 * Proxies to BNIAPIServices bound in the service container.
 *
 * Configuration (config/bni.php):
 *
 *   return [
 *       'client_id'  => env('BNI_CLIENT_ID'),
 *       'secret_key' => env('BNI_SECRET_KEY'),
 *       'prefix'     => env('BNI_PREFIX'),
 *       'url'        => env('BNI_ECOLLECTION_URL', ''),
 *   ];
 *
 * Usage:
 *
 *   use OjiePermana\Laravel\Facades\BNI;
 *
 *   // Create billing
 *   BNI::create(
 *       trxId: 'INV-001',
 *       trxAmount: '100000',
 *       billingType: 'c',
 *       customerName: 'John Doe',
 *       customerEmail: 'john@example.com',
 *       datetimeExpired: '2026-12-31T23:59:00+07:00',
 *   );
 *
 *   // Update billing
 *   BNI::update(trxId: 'INV-001', trxAmount: '200000', customerName: 'John Doe');
 *
 *   // Show billing
 *   BNI::show(trxId: 'INV-001');
 *
 * @method static array create(string $trxId, string $trxAmount, string $billingType, string $customerName, ?string $customerEmail = null, ?string $customerPhone = null, ?string $virtualAccount = null, ?string $datetimeExpired = null, ?string $description = null, bool $sendSms = false)
 * @method static array update(string $trxId, string $trxAmount, string $customerName, ?string $customerEmail = null, ?string $customerPhone = null, ?string $datetimeExpired = null, ?string $description = null)
 * @method static array show(string $trxId)
 *
 * @see BNIAPIServices
 */
class BNI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bni.api';
    }
}
