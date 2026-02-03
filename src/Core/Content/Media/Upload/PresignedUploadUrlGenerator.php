<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Generates presigned S3 URLs for direct uploads bypassing the Shopware server.
 *
 * Supports both explicit credentials and IAM roles (default credential provider chain).
 *
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadUrlGenerator
{
    private ?S3Client $s3Client;

    private ?string $bucket;

    private string $root;

    /**
     * @param array<string, mixed> $filesystemConfig
     */
    public function __construct(
        private AbstractMediaPathStrategy $mediaPathStrategy,
        array $filesystemConfig,
        private int $expirationMinutes = 5,
        private bool $enabled = true,
        ?S3Client $s3Client = null
    ) {
        if (!$this->enabled || ($filesystemConfig['type'] ?? null) !== 'amazon-s3') {
            $this->s3Client = null;
            $this->bucket = null;
            $this->root = '';

            return;
        }

        $s3Config = $filesystemConfig['config'] ?? [];
        if (!\is_array($s3Config)) {
            throw MediaException::presignedUploadInvalidConfiguration('Filesystem config must contain an array of S3 options.');
        }

        $options = $this->resolveS3Options($s3Config);

        $this->bucket = $options['bucket'];
        $this->root = trim($options['root'] ?? '', '/');

        $s3ClientConfig = [
            'region' => $options['region'],
        ];

        if (\array_key_exists('endpoint', $options) && $options['endpoint']) {
            $s3ClientConfig['endpoint'] = $options['endpoint'];
        }

        if (\array_key_exists('use_path_style_endpoint', $options)) {
            $s3ClientConfig['pathStyleEndpoint'] = (string) $options['use_path_style_endpoint'];
        }

        if (isset($options['credentials'])) {
            $s3ClientConfig['accessKeyId'] = $options['credentials']['key'];
            $s3ClientConfig['accessKeySecret'] = $options['credentials']['secret'];
        }

        $this->s3Client = $s3Client ?? new S3Client($s3ClientConfig);
    }

    /**
     * Generate a presigned PUT URL for uploading a file to S3.
     *
     * Use cases:
     * - Direct upload: Client uploads file body directly to the returned URL
     * - Upload from URL: Client fetches remote file and streams it to the returned URL
     *
     * @return array{url: string, path: string, s3Key: string, expiresAt: \DateTimeImmutable}
     */
    public function generate(MediaLocationStruct $location, string $mimeType): array
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

        $paths = $this->mediaPathStrategy->generate([$location]);
        $mediaPath = $paths[$location->id] ?? throw MediaException::strategyNotFound($this->mediaPathStrategy->name());
        $s3Key = $this->ensureRootPrefix($mediaPath);

        $expiresAt = new \DateTimeImmutable(\sprintf('+%d minutes', $this->expirationMinutes));

        if ($this->s3Client === null || $this->bucket === null) {
            throw MediaException::presignedUploadNotSupported();
        }

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

        return [
            'url' => $url,
            'path' => $mediaPath,
            's3Key' => $s3Key,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Check if presigned uploads are enabled via configuration.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if presigned uploads are supported (enabled + S3 configured).
     */
    public function isSupported(): bool
    {
        return $this->enabled && $this->s3Client !== null && $this->bucket !== null;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function resolveS3Options(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'region']);
        $options->setDefined(['credentials', 'root', 'endpoint', 'use_path_style_endpoint']);

        $options->setAllowedTypes('credentials', ['array', 'null']);
        $options->setAllowedTypes('bucket', 'string');
        $options->setAllowedTypes('region', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('endpoint', 'string');
        $options->setAllowedTypes('use_path_style_endpoint', 'bool');

        $options->setDefault('root', '');

        try {
            $config = $options->resolve($definition);
        } catch (ExceptionInterface $e) {
            throw MediaException::presignedUploadInvalidConfiguration($e->getMessage());
        }

        if (\array_key_exists('credentials', $config) && $config['credentials'] !== null && $config['credentials'] !== []) {
            $config['credentials'] = $this->resolveCredentialsOptions($config['credentials']);
        } else {
            unset($config['credentials']);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array{key: string, secret: string}
     */
    private function resolveCredentialsOptions(array $credentials): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['key', 'secret']);

        $options->setAllowedTypes('key', 'string');
        $options->setAllowedTypes('secret', 'string');

        try {
            /** @var array{key: string, secret: string} $resolved */
            $resolved = $options->resolve($credentials);
        } catch (ExceptionInterface $e) {
            throw MediaException::presignedUploadInvalidConfiguration($e->getMessage());
        }

        return $resolved;
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
