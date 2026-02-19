<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Storage;

use DateTime;
use DateTimeInterface;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

class GoogleCloudStorageAdapter implements FilesystemAdapter
{
    private Bucket $bucket;

    public function __construct(
        private readonly StorageClient $client,
        private readonly string $bucketName,
        private readonly string $pathPrefix = '',
        private readonly string $storageApiUri = 'https://storage.googleapis.com',
        private readonly string $defaultVisibility = Visibility::PUBLIC,
    ) {
        $this->bucket = $this->client->bucket($this->bucketName);
    }

    /**
     * Prefix path with configured prefix.
     */
    private function prefixPath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->pathPrefix !== '') {
            return rtrim($this->pathPrefix, '/') . '/' . $path;
        }

        return $path;
    }

    /**
     * Remove prefix from a GCS object name.
     */
    private function unprefixPath(string $path): string
    {
        if ($this->pathPrefix !== '' && str_starts_with($path, rtrim($this->pathPrefix, '/') . '/')) {
            return substr($path, strlen(rtrim($this->pathPrefix, '/')) + 1);
        }

        return $path;
    }

    /**
     * Build upload options based on config.
     */
    private function buildUploadOptions(string $path, Config $config): array
    {
        $options = ['name' => $this->prefixPath($path)];

        $visibility = $config->get(Config::OPTION_VISIBILITY, $this->defaultVisibility);
        if ($visibility === Visibility::PUBLIC) {
            $options['predefinedAcl'] = 'publicRead';
        }

        $mimeType = $config->get('mimetype');
        if ($mimeType) {
            $options['metadata'] = ['contentType' => $mimeType];
        }

        return $options;
    }

    public function fileExists(string $path): bool
    {
        return $this->bucket->object($this->prefixPath($path))->exists();
    }

    public function directoryExists(string $path): bool
    {
        $prefix = rtrim($this->prefixPath($path), '/') . '/';
        $objects = $this->bucket->objects(['prefix' => $prefix, 'maxResults' => 1]);

        foreach ($objects as $object) {
            return true;
        }

        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->bucket->upload($contents, $this->buildUploadOptions($path, $config));
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->bucket->upload($contents, $this->buildUploadOptions($path, $config));
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if (! $object->exists()) {
                throw UnableToReadFile::fromLocation($path, 'File does not exist.');
            }

            return $object->downloadAsString();
        } catch (UnableToReadFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if (! $object->exists()) {
                throw UnableToReadFile::fromLocation($path, 'File does not exist.');
            }

            $stream = $object->downloadAsStream();

            // Detach the underlying PHP resource from the PSR-7 stream
            if (method_exists($stream, 'detach')) {
                $resource = $stream->detach();

                if (is_resource($resource)) {
                    return $resource;
                }
            }

            // Fallback: write to a temp stream
            $resource = fopen('php://temp', 'r+');
            fwrite($resource, (string) $stream);
            rewind($resource);

            return $resource;
        } catch (UnableToReadFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if ($object->exists()) {
                $object->delete();
            }
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $prefix = rtrim($this->prefixPath($path), '/') . '/';
            $objects = $this->bucket->objects(['prefix' => $prefix]);

            foreach ($objects as $object) {
                $object->delete();
            }
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // GCS is a flat object store; simulate directories with a placeholder object
        try {
            $dirPath = rtrim($this->prefixPath($path), '/') . '/';
            $this->bucket->upload('', ['name' => $dirPath]);
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));
            $predefinedAcl = $visibility === Visibility::PUBLIC ? 'publicRead' : 'projectPrivate';
            $object->update(['acl' => []], ['predefinedAcl' => $predefinedAcl]);
        } catch (Throwable $e) {
            throw UnableToSetVisibility::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));
            $acl = $object->acl()->get(['entity' => 'allUsers']);
            $visibility = isset($acl['role']) && $acl['role'] === 'READER'
                ? Visibility::PUBLIC
                : Visibility::PRIVATE;

            return new FileAttributes($path, null, $visibility);
        } catch (Throwable) {
            return new FileAttributes($path, null, Visibility::PRIVATE);
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if (! $object->exists()) {
                throw UnableToRetrieveMetadata::mimeType($path, 'File does not exist.');
            }

            $info = $object->info();
            $mimeType = $info['contentType'] ?? null;

            if (! $mimeType) {
                throw UnableToRetrieveMetadata::mimeType($path, 'MIME type not available.');
            }

            return new FileAttributes($path, null, null, null, $mimeType);
        } catch (UnableToRetrieveMetadata $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if (! $object->exists()) {
                throw UnableToRetrieveMetadata::lastModified($path, 'File does not exist.');
            }

            $info = $object->info();
            $timestamp = isset($info['updated']) ? strtotime($info['updated']) : null;

            return new FileAttributes($path, null, null, $timestamp ?: null);
        } catch (UnableToRetrieveMetadata $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $object = $this->bucket->object($this->prefixPath($path));

            if (! $object->exists()) {
                throw UnableToRetrieveMetadata::fileSize($path, 'File does not exist.');
            }

            $info = $object->info();

            return new FileAttributes($path, (int) ($info['size'] ?? 0));
        } catch (UnableToRetrieveMetadata $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->prefixPath($path);
        $prefix = $prefix !== '' ? rtrim($prefix, '/') . '/' : '';

        $options = ['prefix' => $prefix];

        if (! $deep) {
            $options['delimiter'] = '/';
        }

        $objects = $this->bucket->objects($options);

        foreach ($objects as $object) {
            $objectPath = $this->unprefixPath($object->name());

            if (str_ends_with($object->name(), '/')) {
                yield new DirectoryAttributes(rtrim($objectPath, '/'));
            } else {
                $info = $object->info();
                yield new FileAttributes(
                    $objectPath,
                    (int) ($info['size'] ?? 0),
                    null,
                    isset($info['updated']) ? strtotime($info['updated']) : null,
                    $info['contentType'] ?? null,
                );
            }
        }

        if (! $deep) {
            foreach ($objects->prefixes() as $dirPrefix) {
                yield new DirectoryAttributes($this->unprefixPath(rtrim($dirPrefix, '/')));
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $sourceObject = $this->bucket->object($this->prefixPath($source));

            if (! $sourceObject->exists()) {
                throw UnableToMoveFile::fromLocationTo($source, $destination);
            }

            $sourceObject->copy($this->bucket, ['name' => $this->prefixPath($destination)]);
            $sourceObject->delete();
        } catch (UnableToMoveFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceObject = $this->bucket->object($this->prefixPath($source));

            if (! $sourceObject->exists()) {
                throw UnableToCopyFile::fromLocationTo($source, $destination);
            }

            $sourceObject->copy($this->bucket, ['name' => $this->prefixPath($destination)]);
        } catch (UnableToCopyFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * Get the public URL for a file.
     * Called automatically by Laravel's Storage::url().
     */
    public function getUrl(string $path): string
    {
        $fullPath = $this->prefixPath($path);

        return rtrim($this->storageApiUri, '/') . '/' . $this->bucketName . '/' . ltrim($fullPath, '/');
    }

    /**
     * Generate a signed (temporary) URL for a private file.
     *
     * @param  int|DateTimeInterface  $expiration  Seconds from now or a DateTimeInterface
     */
    public function signedUrl(string $path, int|DateTimeInterface $expiration = 3600): string
    {
        $object = $this->bucket->object($this->prefixPath($path));

        $expires = $expiration instanceof DateTimeInterface
            ? $expiration
            : new DateTime("+{$expiration} seconds");

        return $object->signedUrl($expires);
    }

    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    public function getClient(): StorageClient
    {
        return $this->client;
    }
}
