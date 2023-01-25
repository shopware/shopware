<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use AsyncAws\S3\S3Client;
use AsyncAws\S3\ValueObject\AwsObject;
use AsyncAws\S3\ValueObject\ObjectIdentifier;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal can be removed when https://github.com/thephpleague/flysystem/pull/1621 is removed
 */
#[Package('core')]
class DecoratedAsyncS3Adapter implements FilesystemAdapter, PublicUrlGenerator, ChecksumProvider, TemporaryUrlGenerator
{
    private readonly PathPrefixer $prefixer;

    public function __construct(
        private readonly AsyncAwsS3Adapter $inner,
        private readonly string $bucket,
        private readonly S3Client $client,
        string $prefix
    ) {
        $this->prefixer = new PathPrefixer($prefix);
    }

    public function checksum(string $path, Config $config): string
    {
        return $this->inner->checksum($path, $config);
    }

    public function fileExists(string $path): bool
    {
        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->inner->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = $this->prefixer->prefixDirectoryPath($path);
        $prefix = ltrim($prefix, '/');
        $objects = [];
        $params = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        $result = $this->client->listObjectsV2($params);
        /** @var AwsObject $item */
        foreach ($result->getContents() as $item) {
            $key = $item->getKey();
            if ($key !== null) {
                $objects[] = new ObjectIdentifier(['Key' => $key]);
            }
        }
        if (empty($objects)) {
            return;
        }

        foreach (array_chunk($objects, 1000) as $chunk) {
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => ['Objects' => $chunk],
            ]);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->inner->copy($source, $destination, $config);
    }

    public function publicUrl(string $path, Config $config): string
    {
        return $this->inner->publicUrl($path, $config);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        return $this->inner->temporaryUrl($path, $expiresAt, $config);
    }
}
