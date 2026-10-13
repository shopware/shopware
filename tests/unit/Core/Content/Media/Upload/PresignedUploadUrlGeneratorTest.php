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
use Symfony\Component\HttpClient\MockHttpClient;
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

    public function testGenerateStagingUploadSignsAnUploadOutsideTheMediaPaths(): void
    {
        $generator = $this->createGeneratorWithBothBuckets();

        $stagingUpload = $generator->generateStagingUpload('jpg', false);

        static::assertMatchesRegularExpression('#^presigned-upload/[0-9a-f]{32}\.jpg$#', $stagingUpload->path);
        static::assertStringContainsString($stagingUpload->path, $stagingUpload->url);
        static::assertStringContainsString('public-bucket', $stagingUpload->url);
        static::assertSame('PUT', $stagingUpload->method);
        static::assertSame([], $stagingUpload->headers);
    }

    public function testGenerateStagingUploadForPrivateMediaTargetsThePrivateBucket(): void
    {
        $generator = $this->createGeneratorWithBothBuckets();

        $stagingUpload = $generator->generateStagingUpload('pdf', true);

        static::assertStringContainsString('private-bucket', $stagingUpload->url);
        static::assertStringNotContainsString('public-bucket', $stagingUpload->url);
    }

    public function testGenerateStagingUploadPrefixesTheFilesystemRoot(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', ['root' => 'shop-a', 'credentials' => ['key' => 'test-key', 'secret' => 'test-secret']]),
            new NullLogger(),
            new NativeClock(),
        );

        $stagingUpload = $generator->generateStagingUpload('jpg', false);

        static::assertStringContainsString('shop-a/presigned-upload/', $stagingUpload->url);
        static::assertStringStartsWith('presigned-upload/', $stagingUpload->path);
    }

    public function testGenerateStagingUploadFailsForAnInvalidEndpoint(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', ['endpoint' => 'invalid', 'credentials' => ['key' => 'test-key', 'secret' => 'test-secret']]),
            new NullLogger(),
            new NativeClock(),
        );

        $presignFailure = null;

        try {
            $generator->generateStagingUpload('jpg', false);
        } catch (MediaException $e) {
            $presignFailure = $e;
        }

        static::assertSame(MediaException::MEDIA_PRESIGNED_UPLOAD_FAILED, $presignFailure?->getErrorCode());
    }

    public function testGenerateStagingUploadWhenDisabled(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket'),
            new NullLogger(),
            new NativeClock(),
            enabled: false,
        );

        $this->expectExceptionObject(MediaException::presignedUploadDisabled());

        $generator->generateStagingUpload('jpg', false);
    }

    public function testGenerateStagingUploadWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
        );

        $this->expectExceptionObject(MediaException::presignedUploadNotSupported());

        $generator->generateStagingUpload('jpg', false);
    }

    public function testPromoteCopiesTheStagedObjectWithTheTokenContentTypeAndDeletesTheStagingKey(): void
    {
        $storageRequests = [];
        $generator = $this->createGeneratorForStorageResponses($storageRequests, [
            new MockResponse('<CopyObjectResult><ETag>"abc123"</ETag></CopyObjectResult>'),
            new MockResponse('', ['http_code' => 204]),
        ]);

        static::assertTrue($generator->promote('presigned-upload/staged.svg', 'media/ab/cd/logo.svg', false, 'image/svg+xml', 'abc123', false));

        static::assertCount(2, $storageRequests);
        [$copyRequest, $deleteRequest] = $storageRequests;
        static::assertSame('PUT', $copyRequest['method']);
        static::assertStringEndsWith('/media/ab/cd/logo.svg', $copyRequest['url']);
        static::assertSame(['public-bucket%2Fpresigned-upload%2Fstaged.svg'], $copyRequest['headers']['x-amz-copy-source']);
        static::assertSame(['"abc123"'], $copyRequest['headers']['x-amz-copy-source-if-match']);
        static::assertSame(['REPLACE'], $copyRequest['headers']['x-amz-metadata-directive']);
        static::assertSame(['image/svg+xml'], $copyRequest['headers']['content-type']);
        static::assertSame(['*'], $copyRequest['headers']['if-none-match']);
        static::assertSame('DELETE', $deleteRequest['method']);
        static::assertStringEndsWith('/presigned-upload/staged.svg', $deleteRequest['url']);
    }

    public function testPromoteMayOverwriteAnExistingObjectWhenAllowed(): void
    {
        $storageRequests = [];
        $generator = $this->createGeneratorForStorageResponses($storageRequests, [
            new MockResponse('<CopyObjectResult><ETag>"abc123"</ETag></CopyObjectResult>'),
            new MockResponse('', ['http_code' => 204]),
        ]);

        static::assertTrue($generator->promote('presigned-upload/staged.jpg', 'media/ab/cd/file.jpg', false, 'image/jpeg', 'abc123', true));

        static::assertArrayNotHasKey('if-none-match', $storageRequests[0]['headers']);
    }

    public function testPromoteKeepsTheStagedObjectWhenTheStorageRejectsTheCopy(): void
    {
        $storageRequests = [];
        $generator = $this->createGeneratorForStorageResponses($storageRequests, [
            new MockResponse('<Error><Code>PreconditionFailed</Code></Error>', ['http_code' => 412]),
        ]);

        static::assertFalse($generator->promote('presigned-upload/staged.jpg', 'media/ab/cd/file.jpg', false, 'image/jpeg', 'abc123', false));

        static::assertCount(1, $storageRequests);
    }

    public function testPromoteWhenNotSupported(): void
    {
        $generator = PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            ['type' => 'local'],
            new NullLogger(),
            new NativeClock(),
        );

        static::assertFalse($generator->promote('presigned-upload/staged.jpg', 'media/ab/cd/file.jpg', false, 'image/jpeg', 'abc123', false));
    }

    public function testDeleteAbandonedStagingUploadsDeletesOnlyObjectsOlderThanTheUploadUrls(): void
    {
        $storageRequests = [];
        $generator = $this->createGeneratorForStorageResponses($storageRequests, [
            new MockResponse($this->createStagingListing([
                'presigned-upload/abandoned.jpg' => new \DateTimeImmutable('-1 day'),
                'presigned-upload/in-progress.jpg' => new \DateTimeImmutable('-1 minute'),
            ])),
            new MockResponse('', ['http_code' => 204]),
        ]);

        $generator->deleteAbandonedStagingUploads();

        static::assertCount(2, $storageRequests);
        static::assertSame('GET', $storageRequests[0]['method']);
        static::assertStringContainsString('prefix=presigned-upload%2F', $storageRequests[0]['url']);
        static::assertSame('DELETE', $storageRequests[1]['method']);
        static::assertStringEndsWith('/presigned-upload/abandoned.jpg', $storageRequests[1]['url']);
    }

    public function testDeleteAbandonedStagingUploadsListsEachBucketOnce(): void
    {
        $storageRequests = [];
        $listingWithoutStagedUploads = $this->createStagingListing([]);
        $generator = $this->createGeneratorForStorageResponses(
            $storageRequests,
            [new MockResponse($listingWithoutStagedUploads), new MockResponse($listingWithoutStagedUploads)],
            privateBucket: 'private-bucket',
        );

        $generator->deleteAbandonedStagingUploads();

        static::assertCount(2, $storageRequests);
        static::assertStringContainsString('public-bucket', $storageRequests[0]['url']);
        static::assertStringContainsString('private-bucket', $storageRequests[1]['url']);
    }

    public function testDeleteAbandonedStagingUploadsContinuesWhenTheListingFails(): void
    {
        $storageRequests = [];
        $generator = $this->createGeneratorForStorageResponses($storageRequests, [
            new MockResponse('<Error><Code>AccessDenied</Code></Error>', ['http_code' => 403]),
        ]);

        $generator->deleteAbandonedStagingUploads();

        static::assertCount(1, $storageRequests);
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

    /**
     * @param list<array{method: string, url: string, headers: array<array<string>>}> $storageRequests
     * @param list<MockResponse> $storageResponses
     *
     * @param-out list<array{method: string, url: string, headers: array<array<string>>}> $storageRequests
     */
    private function createGeneratorForStorageResponses(array &$storageRequests, array $storageResponses, ?string $privateBucket = null): PresignedUploadUrlGenerator
    {
        $s3Credentials = ['credentials' => ['key' => 'test-key', 'secret' => 'test-secret']];

        $storageHttpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$storageRequests, &$storageResponses): MockResponse {
            $headerValuesByName = [];
            foreach ($options['normalized_headers'] as $name => $headerLines) {
                $headerValuesByName[$name] = array_map(static fn (string $headerLine): string => substr($headerLine, \strlen($name) + 2), $headerLines);
            }

            $storageRequests[] = ['method' => $method, 'url' => $url, 'headers' => $headerValuesByName];

            return array_shift($storageResponses) ?? throw new \LogicException('Unexpected storage request ' . $method . ' ' . $url);
        });

        return PresignedUploadUrlGenerator::create(
            $this->mediaPathStrategy,
            $this->s3Config('public-bucket', $s3Credentials),
            new NullLogger(),
            new NativeClock(),
            httpClient: $storageHttpClient,
            privateFilesystemConfig: $privateBucket !== null ? $this->s3Config($privateBucket, $s3Credentials) : ['type' => 'local'],
        );
    }

    /**
     * @param array<string, \DateTimeImmutable> $lastModifiedByKey
     */
    private function createStagingListing(array $lastModifiedByKey): string
    {
        $stagedObjectEntries = '';
        foreach ($lastModifiedByKey as $key => $lastModified) {
            $stagedObjectEntries .= \sprintf(
                '<Contents><Key>%s</Key><LastModified>%s</LastModified><ETag>"e"</ETag><Size>1</Size></Contents>',
                $key,
                $lastModified->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            );
        }

        return '<ListBucketResult><IsTruncated>false</IsTruncated>' . $stagedObjectEntries . '</ListBucketResult>';
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
