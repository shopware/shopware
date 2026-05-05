<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\PresignedUploadUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
    private AbstractMediaPathStrategy&MockObject $mediaPathStrategy;

    protected function setUp(): void
    {
        $this->mediaPathStrategy = $this->createMock(AbstractMediaPathStrategy::class);
        $this->mediaPathStrategy->method('name')->willReturn('test-strategy');
    }

    public function testCreateWithDisabledFeature(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'amazon-s3', 'config' => ['bucket' => 'test', 'region' => 'eu-west-1']],
            new NullLogger(),
            enabled: false,
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
        );

        static::assertTrue($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testCreateWithS3FilesystemIsSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );

        static::assertTrue($generator->isEnabled());
        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithExplicitCredentials(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'credentials' => [
                        'key' => 'access-key',
                        'secret' => 'secret-key',
                    ],
                ],
            ],
            new NullLogger(),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithIAMRole(): void
    {
        // No credentials provided - should use IAM role via default credential chain
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithEndpoint(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'endpoint' => 'http://localhost:9000',
                    'use_path_style_endpoint' => true,
                ],
            ],
            new NullLogger(),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithRootPrefix(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'root' => 'shopware/media',
                ],
            ],
            new NullLogger(),
        );

        static::assertTrue($generator->isSupported());
    }

    public function testCreateWithInvalidConfig(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => 'invalid',
            ],
            new NullLogger(),
        );
    }

    public function testCreateWithMissingBucket(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );
    }

    public function testCreateWithMissingRegion(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                ],
            ],
            new NullLogger(),
        );
    }

    public function testCreateWithIncompleteCredentials(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

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
        );
    }

    public function testGenerateWhenDisabled(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            enabled: false,
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Presigned upload is disabled');

        $generator->generate($location, 'image/jpeg');
    }

    public function testGenerateWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Presigned upload is not supported');

        $generator->generate($location, 'image/jpeg');
    }

    public function testGenerateWithNullFileName(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            'jpg',
            null,
            new \DateTimeImmutable()
        );

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The parameter "fileName" is invalid');

        $generator->generate($location, 'image/jpeg');
    }

    public function testGenerateWithNullExtension(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );

        $location = new MediaLocationStruct(
            Uuid::randomHex(),
            null,
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('No file extension provided');

        $generator->generate($location, 'image/jpeg');
    }

    public function testGenerateWithPathGenerationFailure(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaPathStrategy
            ->method('generate')
            ->willReturn([]); // Returns empty array - no path generated

        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
        );

        $location = new MediaLocationStruct(
            $mediaId,
            'jpg',
            'test-file',
            new \DateTimeImmutable()
        );

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('No Strategy with name "test-strategy" found');

        $generator->generate($location, 'image/jpeg');
    }

    public function testGetFileMetadataWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
        );

        static::assertNull($generator->getFileMetadata('media/ab/cd/test.jpg'));
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
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            new NullLogger(),
            httpClient: $httpClient,
        );

        $generator->getFileMetadata('media/test.jpg');
    }
}
