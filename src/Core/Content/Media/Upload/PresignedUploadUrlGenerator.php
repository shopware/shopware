<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use AsyncAws\S3\Input\HeadObjectRequest;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\S3ClientFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadUrlGenerator implements PresignedUrlGeneratorInterface
{
    private function __construct(
        private AbstractMediaPathStrategy $mediaPathStrategy,
        private ?S3Client $s3Client,
        private ?string $bucket,
        private string $root,
        private LoggerInterface $logger,
        private int $expirationMinutes,
        private bool $enabled,
    ) {
    }

    /**
     * @param array<string, mixed> $filesystemConfig
     */
    public static function create(
        AbstractMediaPathStrategy $mediaPathStrategy,
        array $filesystemConfig,
        LoggerInterface $logger,
        int $expirationMinutes = 5,
        bool $enabled = true,
    ): self {
        if (!$enabled || ($filesystemConfig['type'] ?? null) !== 'amazon-s3') {
            return new self($mediaPathStrategy, null, null, '', $logger, $expirationMinutes, $enabled);
        }

        $s3Config = $filesystemConfig['config'] ?? [];
        if (!\is_array($s3Config)) {
            throw MediaException::presignedUploadInvalidConfiguration('Filesystem config must contain an array of S3 options.');
        }

        try {
            $result = S3ClientFactory::create($s3Config);
        } catch (\Throwable $e) {
            throw MediaException::presignedUploadInvalidConfiguration($e->getMessage(), $e);
        }

        return new self(
            $mediaPathStrategy,
            $result['client'],
            $result['bucket'],
            trim($result['root'], '/'),
            $logger,
            $expirationMinutes,
            $enabled,
        );
    }

    public function generate(MediaLocationStruct $location, string $mimeType): PresignedUrlResult
    {
        if (!$this->enabled) {
            throw MediaException::presignedUploadDisabled();
        }

        if (!$this->isSupported()) {
            throw MediaException::presignedUploadNotSupported();
        }

        if ($location->fileName === null) {
            throw MediaException::invalidRequestParameter('fileName');
        }

        if ($location->extension === null) {
            throw MediaException::missingFileExtension();
        }

        if ($this->s3Client === null || $this->bucket === null) {
            throw MediaException::presignedUploadNotSupported();
        }

        $paths = $this->mediaPathStrategy->generate([$location]);
        $mediaPath = $paths[$location->id] ?? throw MediaException::strategyNotFound($this->mediaPathStrategy->name());
        $s3Key = $this->ensureRootPrefix($mediaPath);

        $expiresAt = new \DateTimeImmutable(\sprintf('+%d minutes', $this->expirationMinutes));

        try {
            $request = new PutObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'ContentType' => $mimeType,
            ]);

            $url = $this->s3Client->presign($request, $expiresAt);
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
        return $this->enabled && $this->s3Client !== null && $this->bucket !== null;
    }

    public function verifyUpload(string $path): bool
    {
        if ($this->s3Client === null || $this->bucket === null) {
            return false;
        }

        try {
            $s3Key = $this->ensureRootPrefix($path);

            $request = new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);

            $this->s3Client->headObject($request)->resolve();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getFileMetadata(string $path): ?FileMetadataResult
    {
        if ($this->s3Client === null || $this->bucket === null) {
            return null;
        }

        try {
            $s3Key = $this->ensureRootPrefix($path);

            $request = new HeadObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);

            $result = $this->s3Client->headObject($request);

            return new FileMetadataResult(
                size: $result->getContentLength() ?? 0,
                lastModified: $result->getLastModified() ?? new \DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), [
                'exception' => $e,
                'path' => $path,
            ]);

            return null;
        }
    }

    private function ensureRootPrefix(string $s3Key): string
    {
        if ($this->root === '') {
            return $s3Key;
        }

        if (str_starts_with($s3Key, $this->root . '/')) {
            return $s3Key;
        }

        return $this->root . '/' . ltrim($s3Key, '/');
    }
}
