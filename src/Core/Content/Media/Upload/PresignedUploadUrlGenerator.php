<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Service to generate presigned URLs for direct S3 uploads
 *
 * @internal
 */
#[Package('discovery')]
class PresignedUploadUrlGenerator
{
    private readonly ?S3Client $s3Client;

    private readonly ?string $bucket;

    private readonly ?string $region;

    private readonly bool $enabled;

    /**
     * @param array<string, mixed> $filesystemConfig
     */
    public function __construct(
        private readonly AbstractMediaPathStrategy $mediaPathStrategy,
        array $filesystemConfig,
        private readonly int $expirationMinutes = 5,
        bool $enabled = true
    ) {
        $this->enabled = $enabled;

        // Only initialize S3 if feature is enabled and filesystem type is S3
        if (!$this->enabled || ($filesystemConfig['type'] ?? null) !== 'amazon-s3') {
            $this->s3Client = null;
            $this->bucket = null;
            $this->region = null;
            return;
        }

        $s3Config = $filesystemConfig['config'] ?? [];
        $this->validateS3Config($s3Config);

        $this->bucket = $s3Config['bucket'];
        $this->region = $s3Config['region'];

        $credentials = $s3Config['credentials'] ?? [];

        // Build S3 client config
        $s3ClientConfig = [
            'region' => $this->region,
        ];

        // Only set endpoint if it's explicitly provided and not empty
        $endpoint = $s3Config['endpoint'] ?? null;
        if (!empty($endpoint)) {
            $s3ClientConfig['endpoint'] = $endpoint;
        }

        if (!empty($s3Config['use_path_style_endpoint'])) {
            $s3ClientConfig['pathStyleEndpoint'] = $s3Config['use_path_style_endpoint'];
        }

        // Only add explicit credentials if provided (otherwise AWS SDK uses IAM roles)
        if (!empty($credentials['key']) && !empty($credentials['secret'])) {
            $s3ClientConfig['accessKeyId'] = $credentials['key'];
            $s3ClientConfig['accessKeySecret'] = $credentials['secret'];
        }

        $this->s3Client = new S3Client($s3ClientConfig);
    }

    /**
     * Generate a presigned URL for direct file upload to S3
     *
     * @return array{url: string, mediaId: string, s3Key: string, expiresAt: string}|null
     */
    public function generatePresignedUrl(
        string $fileName,
        string $extension,
        string $mimeType,
        ?string $mediaFolderId = null
    ): ?array {
        if (!$this->enabled) {
            throw new \RuntimeException('Presigned upload feature is disabled. Set "shopware.media.enable_presigned_upload: true" in configuration.');
        }

        if (!$this->isS3Configured()) {
            throw new \RuntimeException('S3 is not configured. Filesystem type must be "amazon-s3" with valid bucket configuration.');
        }

        try {
            $mediaId = Uuid::randomHex();

            $location = new MediaLocationStruct(
                id: $mediaId,
                extension: $extension,
                fileName: pathinfo($fileName, \PATHINFO_FILENAME),
                uploadedAt: new \DateTimeImmutable()
            );

            $paths = $this->mediaPathStrategy->generate([$location]);
            $s3Key = $paths[$mediaId] ?? throw MediaException::strategyNotFound('media-path-strategy');

            $expiresAt = new \DateTimeImmutable("+{$this->expirationMinutes} minutes");

            $putObjectRequest = new PutObjectRequest([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'ContentType' => $mimeType,
            ]);

            $presignedUrl = $this->s3Client->presign(
                $putObjectRequest,
                $expiresAt
            );

            return [
                'url' => $presignedUrl,
                'mediaId' => $mediaId,
                's3Key' => $s3Key,
                'expiresAt' => $expiresAt->format('c'),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to generate presigned URL: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Verify that a file was successfully uploaded to S3
     */
    public function verifyUpload(string $s3Key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get file metadata from S3
     *
     * @return array{size: int, lastModified: string, etag: string}|null
     */
    public function getFileMetadata(string $s3Key): ?array
    {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);

            return [
                'size' => (int) $result->getContentLength(),
                'lastModified' => $result->getLastModified()->format('c'),
            ];
        } catch (\Exception) {
            return null;
        }
    }

    public function isSupported(): bool
    {
        return $this->enabled && $this->isS3Configured();
    }

    /**
     * Validate S3 configuration array
     *
     * @param array<string, mixed> $s3Config
     */
    private function validateS3Config(array $s3Config): void
    {
        if (empty($s3Config['bucket'])) {
            throw new \RuntimeException(
                'S3 bucket is not configured. Check your shopware.yaml: ' .
                'shopware.filesystem.public.config.bucket must be set.'
            );
        }

        if (empty($s3Config['region'])) {
            throw new \RuntimeException(
                'S3 region is not configured. Check your shopware.yaml: ' .
                'shopware.filesystem.public.config.region must be set.'
            );
        }

        // Credentials are optional - AWS SDK will use IAM roles if not provided
        // If credentials are provided, validate them
        $credentials = $s3Config['credentials'] ?? [];
        if (!empty($credentials)) {
            if (empty($credentials['key']) || empty($credentials['secret'])) {
                throw new \RuntimeException(
                    'S3 credentials are incomplete. If providing credentials, both key and secret are required. ' .
                    'Alternatively, remove credentials to use IAM roles.'
                );
            }
        }
    }

    /**
     * Check if S3 is properly configured
     */
    private function isS3Configured(): bool
    {
        return !empty($this->bucket);
    }
}
