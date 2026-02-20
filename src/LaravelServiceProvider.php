<?php

namespace OjiePermana\Laravel;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;
use OjiePermana\Laravel\Directives\CurrencyDirective;
use OjiePermana\Laravel\Bank\BNI\Billing\BniBillingClient;
use OjiePermana\Laravel\Storage\GoogleCloudStorageAdapter;
use RuntimeException;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CurrencyDirective::register();

        $this->publishes([
            __DIR__ . '/../config/bni.php' => config_path('bni.php'),
        ], 'bni-config');

        Storage::extend('gcs', function ($app, array $config) {
            $clientConfig = ['projectId' => $config['project_id'] ?? null];

            if (! empty($config['key_file'])) {
                $clientConfig['keyFilePath'] = $config['key_file'];
            } elseif (! empty($config['key_file_json'])) {
                $clientConfig['keyFile'] = $config['key_file_json'];
            }

            $client = new StorageClient($clientConfig);

            $adapter = new GoogleCloudStorageAdapter(
                client: $client,
                bucketName: $config['bucket'],
                pathPrefix: $config['path_prefix'] ?? '',
                storageApiUri: $config['storage_api_uri'] ?? 'https://storage.googleapis.com',
                defaultVisibility: $config['visibility'] === 'private' ? Visibility::PRIVATE : Visibility::PUBLIC,
            );

            $flysystem = new Filesystem($adapter, [
                'visibility' => $config['visibility'] ?? Visibility::PUBLIC,
            ]);

            return new FilesystemAdapter($flysystem, $adapter, $config);
        });
    }

    public function register(): void
    {
        require_once __DIR__ . '/Helpers/IndonesiaHelper.php';

        $this->mergeConfigFrom(__DIR__ . '/../config/bni.php', 'bni');

        $this->app->singleton('bni.api', function ($app) {
            $config = $app['config']['bni'];
            $billing = $config['billing'] ?? $config;

            return new BniBillingClient(
                clientId:  $billing['client_id']  ?? '',
                secretKey: $billing['secret_key'] ?? '',
                prefix:    $billing['prefix']     ?? '',
                url:       $billing['url']        ?? '',
            );
        });

        // Bind 'gcs' to the default GCS disk so the GCS Facade works
        $this->app->singleton('gcs', function ($app) {
            $configuredDefaultDisk = $app['config']->get('filesystems.gcs_default_disk');

            if (is_string($configuredDefaultDisk) && $configuredDefaultDisk !== '') {
                return $app['filesystem']->disk($configuredDefaultDisk);
            }

            $disks = $app['config']->get('filesystems.disks', []);

            if (is_array($disks)) {
                foreach ($disks as $diskName => $diskConfig) {
                    if (! is_array($diskConfig)) {
                        continue;
                    }

                    if (($diskConfig['driver'] ?? null) === 'gcs') {
                        return $app['filesystem']->disk($diskName);
                    }
                }
            }

            throw new RuntimeException(
                "Unable to resolve GCS disk for binding [gcs]. Set [filesystems.gcs_default_disk] or define at least one disk in [filesystems.disks] with driver [gcs]."
            );
        });
    }
}
