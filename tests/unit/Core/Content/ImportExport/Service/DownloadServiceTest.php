<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\AssertResponseHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(DownloadService::class)]
class DownloadServiceTest extends TestCase
{
    public const DEFAULT_STRATEGY = 'php';

    #[DataProvider('dataProviderInvalidAccessToken')]
    public function testInvalidAccessToken(ImportExportFileEntity $fileEntity, string $accessToken): void
    {
        $this->expectExceptionObject(ImportExportException::invalidFileAccessToken());
        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = new StaticEntityRepository([new EntityCollection([$fileEntity])]);

        $downloadService = $this->createDownloadService(fileRepository: $fileRepository);

        $downloadService->createFileResponse(Context::createDefaultContext(), $fileEntity->getId(), $accessToken);
    }

    #[DataProvider('dataProviderNotFoundFile')]
    public function testNotFoundFile(ImportExportFileEntity $fileEntity, string $accessToken, string $fileId): void
    {
        $this->expectExceptionObject(ImportExportException::fileNotFound($fileId));

        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = new StaticEntityRepository([new EntityCollection([$fileEntity])]);

        $downloadService = $this->createDownloadService(fileRepository: $fileRepository);

        $downloadService->createFileResponse(Context::createDefaultContext(), $fileId, $accessToken);
    }

    #[DataProvider('dataProviderCreateFileResponse')]
    public function testCreateFileResponse(
        ImportExportFileEntity $fileEntity,
        string $accessToken,
        string $fileId,
        string $expectOutputFilename,
        string $expectedContentType
    ): void {
        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = new StaticEntityRepository([new EntityCollection([$fileEntity])]);

        $fileSystem = $this->createFileSystem();
        $fileSystem->expects($this->once())->method('readStream')->willReturn(fopen('php://memory', 'r'));
        $fileSystem->expects($this->once())->method('fileSize')->willReturn(100);

        $downloadService = $this->createDownloadService(
            fileSystem: $fileSystem,
            fileRepository: $fileRepository
        );

        $response = $downloadService->createFileResponse(Context::createDefaultContext(), $fileId, $accessToken);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($header = $response->headers->get('Content-Disposition'));
        static::assertStringContainsString($expectOutputFilename, $header);
        static::assertSame($expectedContentType, $response->headers->get('Content-Type'));
    }

    #[DataProvider('dataProviderDownloadStrategies')]
    public function testCreateFileResponseWithDownloadStrategies(
        string $strategy,
        Response $expectedResponse,
        string $localPathPrefix = ''
    ): void {
        $fileId = Uuid::randomHex();
        $fileEntity = (new ImportExportFileEntity())->assign([
            'id' => $fileId,
            'originalName' => 'products.csv',
            'accessToken' => 'validAccessToken',
            'path' => 'export/foobar.txt',
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = new StaticEntityRepository([new EntityCollection([$fileEntity])]);

        $fileSystem = $this->createFileSystem();
        $fileSystem->method('temporaryUrl')->willThrowException(new UnableToGenerateTemporaryUrl('reason', '/any/path'));
        $fileSystem->method('fileSize')->willReturn(100);

        if ($strategy === DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY) {
            $stream = fopen('php://memory', 'r+');
            static::assertIsResource($stream);
            fwrite($stream, 'test');
            rewind($stream);
            $fileSystem->method('readStream')->willReturn($stream);
            $expectedResponse->headers->set(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY, 'php://memory');
        } else {
            $fileSystem->expects($this->never())->method('readStream');
        }

        $downloadService = $this->createDownloadService(
            fileSystem: $fileSystem,
            fileRepository: $fileRepository,
            localDownloadStrategy: $strategy,
            localPathPrefix: $localPathPrefix,
        );

        $response = $downloadService->createFileResponse(Context::createDefaultContext(), $fileId, 'validAccessToken');

        AssertResponseHelper::assertResponseEquals($expectedResponse, $response);
    }

    /**
     * @return iterable<string, array{strategy: string, expectedResponse: Response, localPathPrefix?: string}>
     */
    public static function dataProviderDownloadStrategies(): iterable
    {
        yield 'x-sendfile' => [
            'strategy' => DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY,
            'expectedResponse' => self::createExpectedResponse(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY),
        ];

        yield 'x-accel' => [
            'strategy' => DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY,
            'expectedResponse' => self::createExpectedResponse(DownloadResponseGenerator::X_ACCEL_REDIRECT),
        ];

        yield 'x-accel with prefix' => [
            'strategy' => DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY,
            'expectedResponse' => self::createExpectedResponse(DownloadResponseGenerator::X_ACCEL_REDIRECT, '/any/Path'),
            'localPathPrefix' => '/any/Path',
        ];
    }

    public function testCreateFileResponseUsesTemporaryUrlWhenAvailable(): void
    {
        $fileId = Uuid::randomHex();
        $fileEntity = (new ImportExportFileEntity())->assign([
            'id' => $fileId,
            'originalName' => 'products.csv',
            'accessToken' => 'validAccessToken',
            'path' => 'export/foobar.txt',
            'updatedAt' => new \DateTimeImmutable(),
        ]);

        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = new StaticEntityRepository([new EntityCollection([$fileEntity])]);

        $fileSystem = $this->createMock(Filesystem::class);
        $fileSystem->expects($this->once())->method('temporaryUrl')->with(
            'export/foobar.txt',
            static::isInstanceOf(\DateTimeImmutable::class),
            [
                'get_object_options' => [
                    'ResponseContentDisposition' => HeaderUtils::makeDisposition(
                        HeaderUtils::DISPOSITION_ATTACHMENT,
                        'products.csv',
                        'products.csv'
                    ),
                    'ResponseContentType' => 'text/csv',
                ],
            ]
        )->willReturn('https://example.com/download');
        $fileSystem->expects($this->never())->method('readStream');
        $fileSystem->expects($this->never())->method('fileSize');

        $downloadService = $this->createDownloadService(
            fileSystem: $fileSystem,
            fileRepository: $fileRepository,
        );

        $response = $downloadService->createFileResponse(Context::createDefaultContext(), $fileId, 'validAccessToken');

        AssertResponseHelper::assertResponseEquals(new RedirectResponse('https://example.com/download'), $response);
    }

    /**
     * @return iterable<string, array{fileEntity: ImportExportFileEntity, accessToken: string}>
     */
    public static function dataProviderInvalidAccessToken(): iterable
    {
        yield 'empty access token' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => Uuid::randomHex(),
                'accessToken' => '',
            ]),
            'accessToken' => 'validAccessToken',
        ];

        yield 'mismatched access token' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => Uuid::randomHex(),
                'accessToken' => 'validAccessToken',
            ]),
            'accessToken' => 'inValidAccessToken',
        ];

        yield 'recently modified access token' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => Uuid::randomHex(),
                'accessToken' => 'validAccessToken',
                'updatedAt' => new \DateTimeImmutable('+' . 600),
            ]),
            'accessToken' => 'validAccessToken',
        ];
    }

    /**
     * @return iterable<string, array{fileEntity: ImportExportFileEntity, accessToken: string, fileId: string}>
     */
    public static function dataProviderNotFoundFile(): iterable
    {
        $fileId = Uuid::randomHex();
        $notFoundFileId = Uuid::randomHex();

        yield 'fileId not found' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'fileName',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $notFoundFileId,
        ];

        yield 'file not found' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'fileName',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
        ];
    }

    /**
     * @return iterable<string, array{
     *     fileEntity: ImportExportFileEntity,
     *     accessToken: string,
     *     fileId: string,
     *     expectOutputFilename: string,
     *     expectedContentType: string
     * }>
     */
    public static function dataProviderCreateFileResponse(): iterable
    {
        $fileId = Uuid::randomHex();

        yield 'Name with non-ascii chars' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'Name with öäüß',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
            'expectOutputFilename' => 'Name with',
            'expectedContentType' => 'application/octet-stream',
        ];

        yield 'Name with ascii chars' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'Name with ascii chars',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
            'expectOutputFilename' => 'Name with',
            'expectedContentType' => 'application/octet-stream',
        ];

        yield 'Name with slashes chars' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'Name with /\/\/\ slashes',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
            'expectOutputFilename' => 'Name with  slashes',
            'expectedContentType' => 'application/octet-stream',
        ];

        yield 'CSV file name' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'products.csv',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
            'expectOutputFilename' => 'products.csv',
            'expectedContentType' => 'text/csv',
        ];

        yield 'CSV file name with uppercase extension' => [
            'fileEntity' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'originalName' => 'products.CSV',
                'accessToken' => 'validAccessToken',
                'path' => 'path',
                'updatedAt' => new \DateTimeImmutable(),
            ]),
            'accessToken' => 'validAccessToken',
            'fileId' => $fileId,
            'expectOutputFilename' => 'products.CSV',
            'expectedContentType' => 'text/csv',
        ];
    }

    private static function createExpectedResponse(?string $strategy = null, string $localPathPrefix = ''): Response
    {
        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'products.csv',
                'products.csv'
            ),
            'Content-Length' => 100,
            'Content-Type' => 'text/csv',
        ];

        if ($strategy) {
            $response = new Response(null, Response::HTTP_OK, $headers);

            $location = 'export/foobar.txt';
            if ($strategy === DownloadResponseGenerator::X_ACCEL_REDIRECT && $localPathPrefix !== '') {
                $location = $localPathPrefix . '/export/foobar.txt';
            }

            $response->headers->set($strategy, $location);

            return $response;
        }

        return new StreamedResponse(static function (): void {
        }, Response::HTTP_OK, $headers);
    }

    private function createFileSystem(): Filesystem&MockObject
    {
        $fileSystemMock = $this->createMock(Filesystem::class);
        $fileSystemMock->method('temporaryUrl')->willReturn('');

        return $fileSystemMock;
    }

    /**
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>>|null $fileRepository
     */
    private function createDownloadService(
        ?FilesystemOperator $fileSystem = null,
        ?EntityRepository $fileRepository = null,
        ?LoggerInterface $logger = null,
        string $localDownloadStrategy = self::DEFAULT_STRATEGY,
        string $localPathPrefix = ''
    ): DownloadService {
        $fileSystem ??= $this->createFileSystem();
        $fileRepository ??= $this->createFileRepository();
        $logger ??= static::createStub(LoggerInterface::class);

        return new DownloadService(
            $fileSystem,
            $fileRepository,
            $logger,
            $localDownloadStrategy,
            $localPathPrefix,
            new NativeClock()
        );
    }

    /**
     * @return StaticEntityRepository<EntityCollection<ImportExportFileEntity>>
     */
    private function createFileRepository(): StaticEntityRepository
    {
        /** @var StaticEntityRepository<EntityCollection<ImportExportFileEntity>> */
        return new StaticEntityRepository([]);
    }
}
