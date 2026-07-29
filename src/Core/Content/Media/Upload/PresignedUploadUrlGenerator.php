<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\HeadObjectRequest;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\S3ClientFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 *
 * @phpstan-type S3Target array{client: ?S3Client, bucket: ?string, root: string}
 */
#[Package('discovery')]
readonly class PresignedUploadUrlGenerator implements PresignedUrlGeneratorInterface
{
    /**
     * @param S3Target $publicTarget
     * @param S3Target $privateTarget
     */
    private function __construct(
        private AbstractMediaPathStrategy $mediaPathStrategy,
        private array $publicTarget,
        private array $privateTarget,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private int $expirationMinutes,
        private bool $enabled,
    ) {
    }

    /**
     * @param array<string, mixed> $publicFilesystemConfig config of the filesystem used for public media
     * @param array<string, mixed> $privateFilesystemConfig config of the filesystem used for private media
     */
    public static function create(
        AbstractMediaPathStrategy $mediaPathStrategy,
        array $publicFilesystemConfig,
        LoggerInterface $logger,
        ClockInterface $clock,
        ?HttpClientInterface $httpClient = null,
        int $expirationMinutes = 5,
        bool $enabled = true,
        array $privateFilesystemConfig = [],
    ): self {
        return new self(
            $mediaPathStrategy,
            self::resolveTarget($publicFilesystemConfig, $enabled, $httpClient),
            self::resolveTarget($privateFilesystemConfig, $enabled, $httpClient),
            $logger,
            $clock,
            $expirationMinutes,
            $enabled,
        );
    }

    public function generate(MediaLocationStruct $location, string $mimeType, bool $private): PresignedUrlResult
    {
        if (!$this->isEnabled()) {
            throw MediaException::presignedUploadDisabled();
        }

        ['client' => $client, 'bucket' => $bucket, 'root' => $root] = $this->target($private);

        if ($client === null || $bucket === null) {
            throw MediaException::presignedUploadNotSupported();
        }

        if ($location->fileName === null) {
            throw MediaException::invalidRequestParameter('fileName');
        }

        if ($location->extension === null) {
            throw MediaException::missingFileExtension();
        }

        $paths = $this->mediaPathStrategy->generate([$location]);
        $mediaPath = $paths[$location->id] ?? throw MediaException::strategyNotFound($this->mediaPathStrategy->name());
        $s3Key = $this->ensureRootPrefix($mediaPath, $root);

        $expiresAt = $this->clock->now()->modify(\sprintf('+%d minutes', $this->expirationMinutes));

        try {
            // The presigned ContentType must match the `Content-Type` header the browser sends on the PUT
            // byte-for-byte, otherwise S3 rejects the upload with `SignatureDoesNotMatch`. The client applies the
            // same charset canonicalization before sending — see `withCharset()` in
            // src/Administration/Resources/app/administration/src/core/service/api/media-presigned-upload.api.service.js
            $request = new PutObjectRequest([
                'Bucket' => $bucket,
                'Key' => $s3Key,
                'ContentType' => FileInfoHelper::addCharset($mimeType),
            ]);

            $url = $client->presign($request, $expiresAt);
        } catch (\Throwable $e) {
            throw MediaException::presignedUploadFailed($e);
        }

        return new PresignedUrlResult(
            url: $url,
            path: $mediaPath,
            expiresAt: $expiresAt,
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isSupported(): bool
    {
        return $this->enabled
            && $this->isTargetSupported($this->publicTarget)
            && $this->isTargetSupported($this->privateTarget);
    }

    public function getFileMetadata(string $path, bool $private): ?FileMetadataResult
    {
        ['client' => $client, 'bucket' => $bucket, 'root' => $root] = $this->target($private);

        if ($client === null || $bucket === null) {
            return null;
        }

        try {
            $s3Key = $this->ensureRootPrefix($path, $root);

            $request = new HeadObjectRequest([
                'Bucket' => $bucket,
                'Key' => $s3Key,
            ]);

            $result = $client->headObject($request);

            $etag = $result->getEtag();
            if ($etag !== null) {
                $etag = trim($etag, '"');
            }

            return new FileMetadataResult(
                size: (int) ($result->getContentLength()),
                lastModified: $result->getLastModified() ?? $this->clock->now(),
                etag: $etag,
                contentType: $result->getContentType(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), [
                'exception' => $e,
                'path' => $path,
            ]);

            return null;
        }
    }

    public function deleteFromStorage(string $path, bool $private): void
    {
        ['client' => $client, 'bucket' => $bucket, 'root' => $root] = $this->target($private);

        if ($client === null || $bucket === null) {
            return;
        }

        try {
            $s3Key = $this->ensureRootPrefix($path, $root);

            $request = new DeleteObjectRequest([
                'Bucket' => $bucket,
                'Key' => $s3Key,
            ]);

            $client->deleteObject($request)->resolve();
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to delete orphaned presigned upload at path "{path}": {message}', [
                'path' => $path,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $filesystemConfig
     *
     * @return S3Target
     */
    private static function resolveTarget(array $filesystemConfig, bool $enabled, ?HttpClientInterface $httpClient): array
    {
        if (!$enabled || ($filesystemConfig['type'] ?? null) !== 'amazon-s3') {
            return ['client' => null, 'bucket' => null, 'root' => ''];
        }

        $s3Config = $filesystemConfig['config'] ?? [];
        if (!\is_array($s3Config)) {
            throw MediaException::presignedUploadInvalidConfiguration('Filesystem config must contain an array of S3 options.');
        }

        try {
            $result = S3ClientFactory::create($s3Config, $httpClient);
        } catch (\Throwable $e) {
            throw MediaException::presignedUploadInvalidConfiguration($e->getMessage(), $e);
        }

        return [
            'client' => $result['client'],
            'bucket' => $result['bucket'],
            'root' => trim($result['root'], '/'),
        ];
    }

    /**
     * @return S3Target
     */
    private function target(bool $private): array
    {
        return $private ? $this->privateTarget : $this->publicTarget;
    }

    /**
     * @param S3Target $target
     */
    private function isTargetSupported(array $target): bool
    {
        return $target['client'] !== null && $target['bucket'] !== null;
    }

    private function ensureRootPrefix(string $s3Key, string $root): string
    {
        if ($root === '') {
            return $s3Key;
        }

        if (str_starts_with($s3Key, $root . '/')) {
            return $s3Key;
        }

        return $root . '/' . ltrim($s3Key, '/');
    }
}
