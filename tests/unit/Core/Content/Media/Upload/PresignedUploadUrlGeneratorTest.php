<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\PresignedUploadUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadUrlGenerator::class)]
class PresignedUploadUrlGeneratorTest extends TestCase
{
    private AbstractMediaPathStrategy&Stub $mediaPathStrategy;

    protected function setUp(): void
    {
        $this->mediaPathStrategy = static::createStub(AbstractMediaPathStrategy::class);
        $this->mediaPathStrategy->method('name')->willReturn('test-strategy');
    }

    public function testCreateWithDisabledFeature(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            enabled: false,
            privateFilesystemConfig: $this->s3Config('private-bucket'),
        );

        static::assertFalse($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testCreateWithNonS3Filesystem(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: ['type' => 'local'],
        );

        static::assertTrue($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testCreateWithBothS3FilesystemsIsSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket'),
        );

        static::assertTrue($generator->isEnabled());
        static::assertTrue($generator->isSupported());
    }

    public function testIsSupportedIsFalseWhenOnlyPublicIsS3(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: ['type' => 'local'],
        );

        // The admin toggles presign globally with no per-upload fallback, so a half-configured setup must
        // report unsupported and fall back to the regular upload path for every upload.
        static::assertFalse($generator->isSupported());
    }

    public function testIsSupportedIsFalseWhenOnlyPrivateIsS3(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket'),
        );

        static::assertFalse($generator->isSupported());
    }

    public function testCreateWithExplicitCredentials(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', ['credentials' => ['key' => 'access-key', 'secret' => 'secret-key']]),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket', ['credentials' => ['key' => 'access-key', 'secret' => 'secret-key']]),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithIAMRole(): void
    {
        // No credentials provided - should use IAM role via default credential chain
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket'),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithEndpoint(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', ['endpoint' => 'http://localhost:9000', 'use_path_style_endpoint' => true]),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket', ['endpoint' => 'http://localhost:9000', 'use_path_style_endpoint' => true]),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithRootPrefix(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', ['root' => 'shopware/media']),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket', ['root' => 'shopware/media']),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithInvalidConfig(): void
    {
        $this->expectExceptionObject(MediaException::presignedUploadInvalidConfiguration(''));

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => 'invalid',
            ],
            new NullLogger(),
            new NativeClock(),
        );
    }

    public function testCreateWithMissingBucket(): void
    {
        $this->expectExceptionObject(MediaException::presignedUploadInvalidConfiguration(''));

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
            new NativeClock(),
        );
    }

    public function testCreateWithMissingRegion(): void
    {
        $this->expectExceptionObject(MediaException::presignedUploadInvalidConfiguration(''));

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                ],
            ],
            new NullLogger(),
            new NativeClock(),
        );
    }

    public function testCreateWithIncompleteCredentials(): void
    {
        $this->expectExceptionObject(MediaException::presignedUploadInvalidConfiguration(''));

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'credentials' => [
                        'key' => 'access-key',
                        // missing secret
                    ],
                ],
            ],
            new NullLogger(),
            new NativeClock(),
        );
    }

    public function testGenerateWhenDisabled(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
            enabled: false,
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::presignedUploadDisabled());

        $generator->generate($location, 'image/jpeg', false);
    }

    public function testGenerateWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::presignedUploadNotSupported());

        $generator->generate($location, 'image/jpeg', false);
    }

    public function testGeneratePrivateThrowsWhenPrivateFilesystemIsNotS3(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: ['type' => 'local'],
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::presignedUploadNotSupported());

        $generator->generate($location, 'image/jpeg', true);
    }

    public function testGenerateWithNullFileName(): void
    {
        $generator = $this->createGeneratorWithBothBuckets();

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            null,
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::invalidRequestParameter('fileName'));

        $generator->generate($location, 'image/jpeg', false);
    }

    public function testGenerateWithNullExtension(): void
    {
        $generator = $this->createGeneratorWithBothBuckets();

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            null,
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::missingFileExtension());

        $generator->generate($location, 'image/jpeg', false);
    }

    public function testGenerateWithPathGenerationFailure(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaPathStrategy
            ->method('generate')
            ->willReturn([]); // Returns empty array - no path generated

        $generator = $this->createGeneratorWithBothBuckets();

        $location = new MediaLocationStruct(
            $mediaId,
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectExceptionObject(MediaException::strategyNotFound('test-strategy'));

        $generator->generate($location, 'image/jpeg', false);
    }

    public function testGeneratePublicUploadTargetsPublicBucket(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => 'media/ab/cd/file.jpg']);

        $generator = $this->createGeneratorWithBothBuckets();

        $location = new MediaLocationStruct($mediaId, 'jpg', 'file', new \DateTimeImmutable());

        $result = $generator->generate($location, 'image/jpeg', false);

        static::assertStringContainsString('public-bucket', $result->url);
        static::assertStringNotContainsString('private-bucket', $result->url);
    }

    public function testGeneratePrivateUploadTargetsPrivateBucket(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaPathStrategy->method('generate')->willReturn([$mediaId => 'media/ab/cd/file.jpg']);

        $generator = $this->createGeneratorWithBothBuckets();

        $location = new MediaLocationStruct($mediaId, 'jpg', 'file', new \DateTimeImmutable());

        $result = $generator->generate($location, 'image/jpeg', true);

        static::assertStringContainsString('private-bucket', $result->url);
        static::assertStringNotContainsString('public-bucket', $result->url);
    }

    public function testGetFileMetadataWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
        );

        static::assertNull($generator->getFileMetadata('media/ab/cd/test.jpg', false));
    }

    public function testCreateWithCustomHttpClient(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options = []): ResponseInterface {
                static::assertSame('HEAD', $method);
                static::assertNotSame('', $url);

                return new MockResponse('', ['http_code' => 200]);
            });

        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            // Provide static credentials so the async-aws client does not fall back to the EC2
            // instance-metadata credential provider, whose IMDSv2 token request is a PUT and would
            // be the first call hitting the mocked HTTP client instead of the HEAD.
            $this->s3Config('public-bucket', ['credentials' => ['key' => 'test-key', 'secret' => 'test-secret']]),
            new NullLogger(),
            new NativeClock(),
            httpClient: $httpClient,
        );

        $generator->getFileMetadata('media/test.jpg', false);
    }

    private function createGeneratorWithBothBuckets(): PresignedUploadUrlGenerator
    {
        $credentials = ['credentials' => ['key' => 'test-key', 'secret' => 'test-secret']];

        return PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', $credentials),
            new NullLogger(),
            new NativeClock(),
            privateFilesystemConfig: $this->s3Config('private-bucket', $credentials),
        );
    }

    /**
     * @param array<string, mixed> $extraConfig
     *
     * @return array{type: string, config: array<string, mixed>}
     */
    private function s3Config(string $bucket, array $extraConfig = []): array
    {
        return [
            'type' => 'amazon-s3',
            'config' => array_merge([
                'bucket' => $bucket,
                'region' => 'eu-west-1',
            ], $extraConfig),
        ];
    }
}
