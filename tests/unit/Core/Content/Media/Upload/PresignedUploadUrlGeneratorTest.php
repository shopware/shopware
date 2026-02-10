<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\PresignedUploadUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

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
            5,
            false
        );

        static::assertFalse($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testCreateWithNonS3Filesystem(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            5,
            true
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
            5,
            true
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
            5,
            true
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
            5,
            true
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
            5,
            true
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
            5,
            true
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
            ]
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
            ]
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
            ]
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
            ]
        );
    }

    public function testGenerateWhenDisabled(): void
    {
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            null,
            null,
            '',
            5,
            false
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
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            null,
            null,
            '',
            5,
            true
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
            5,
            true
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
            5,
            true
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
            5,
            true
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

    public function testVerifyUploadWhenNotSupported(): void
    {
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            null,
            null,
            '',
            5,
            true
        );

        static::assertFalse($generator->verifyUpload('media/ab/cd/test.jpg'));
    }

    public function testGetFileMetadataWhenNotSupported(): void
    {
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            null,
            null,
            '',
            5,
            true
        );

        static::assertNull($generator->getFileMetadata('media/ab/cd/test.jpg'));
    }
}
