<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testConstructorWithDisabledFeature(): void
    {
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            ['type' => 'amazon-s3', 'config' => ['bucket' => 'test', 'region' => 'eu-west-1']],
            5,
            false
        );

        static::assertFalse($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testConstructorWithNonS3Filesystem(): void
    {
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            5,
            true
        );

        static::assertTrue($generator->isEnabled());
        static::assertFalse($generator->isSupported());
    }

    public function testConstructorWithS3FilesystemIsSupported(): void
    {
        $generator = new PresignedUploadUrlGenerator(
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

    public function testConstructorWithExplicitCredentials(): void
    {
        $generator = new PresignedUploadUrlGenerator(
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

    public function testConstructorWithIAMRole(): void
    {
        // No credentials provided - should use IAM role via default credential chain
        $generator = new PresignedUploadUrlGenerator(
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

    public function testConstructorWithEmptyCredentials(): void
    {
        // Empty credentials should be treated as IAM role
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'credentials' => [],
                ],
            ],
            5,
            true
        );

        static::assertTrue($generator->isSupported());
    }

    public function testConstructorWithNullCredentials(): void
    {
        // Null credentials should be treated as IAM role
        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                    'credentials' => null,
                ],
            ],
            5,
            true
        );

        static::assertTrue($generator->isSupported());
    }

    public function testConstructorWithEndpoint(): void
    {
        $generator = new PresignedUploadUrlGenerator(
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

    public function testConstructorWithRootPrefix(): void
    {
        $generator = new PresignedUploadUrlGenerator(
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

    public function testConstructorWithInvalidConfig(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => 'invalid',
            ]
        );
    }

    public function testConstructorWithMissingBucket(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'region' => 'eu-west-1',
                ],
            ]
        );
    }

    public function testConstructorWithMissingRegion(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                ],
            ]
        );
    }

    public function testConstructorWithIncompleteCredentials(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('Invalid presigned upload configuration');

        new PresignedUploadUrlGenerator(
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
            ['type' => 'amazon-s3', 'config' => ['bucket' => 'test', 'region' => 'eu-west-1']],
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
            ['type' => 'local'],
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
        $generator = new PresignedUploadUrlGenerator(
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
        $generator = new PresignedUploadUrlGenerator(
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

        $generator = new PresignedUploadUrlGenerator(
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

    public function testIsEnabled(): void
    {
        $enabledGenerator = new PresignedUploadUrlGenerator(
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

        $disabledGenerator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            5,
            false
        );

        static::assertTrue($enabledGenerator->isEnabled());
        static::assertFalse($disabledGenerator->isEnabled());
    }

    public function testIsSupported(): void
    {
        $supportedGenerator = new PresignedUploadUrlGenerator(
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

        $notSupportedGenerator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            5,
            true
        );

        $disabledGenerator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            [
                'type' => 'amazon-s3',
                'config' => [
                    'bucket' => 'test-bucket',
                    'region' => 'eu-west-1',
                ],
            ],
            5,
            false
        );

        static::assertTrue($supportedGenerator->isSupported());
        static::assertFalse($notSupportedGenerator->isSupported());
        static::assertFalse($disabledGenerator->isSupported());
    }

    #[DataProvider('filesystemTypeProvider')]
    public function testIsSupportedWithDifferentFilesystemTypes(string $type, bool $expectedSupported): void
    {
        $config = ['type' => $type];

        if ($type === 'amazon-s3') {
            $config['config'] = [
                'bucket' => 'test-bucket',
                'region' => 'eu-west-1',
            ];
        }

        $generator = new PresignedUploadUrlGenerator(
            $this->mediaPathStrategy,
            $config,
            5,
            true
        );

        static::assertSame($expectedSupported, $generator->isSupported());
    }

    /**
     * @return iterable<string, array{type: string, expectedSupported: bool}>
     */
    public static function filesystemTypeProvider(): iterable
    {
        yield 'amazon-s3' => [
            'type' => 'amazon-s3',
            'expectedSupported' => true,
        ];

        yield 'local' => [
            'type' => 'local',
            'expectedSupported' => false,
        ];
    }
}
