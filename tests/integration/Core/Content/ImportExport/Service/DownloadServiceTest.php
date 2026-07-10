<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Service;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileAccessTokenException;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DownloadServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const DEFAULT_STRATEGY = 'php';

    public function testUtf8Filename(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = static::getContainer()->get('import_export_file.repository');

        $asciiName = 'Name with non-ascii chars';

        $fileData = [
            'id' => Uuid::randomHex(),
            'originalName' => $asciiName . ' öäüß',
            'path' => 'test.csv',
            'expireDate' => new \DateTime(),
        ];
        $filesystem->write($fileData['path'], $fileData['originalName']);
        $context = Context::createDefaultContext();
        $fileRepository->create([$fileData], $context);

        $downloadService = $this->createDownloadService($filesystem, $fileRepository);
        $accessToken = $downloadService->regenerateToken($context, $fileData['id']);

        $response = $downloadService->createFileResponse($context, $fileData['id'], $accessToken);
        static::assertIsString($header = $response->headers->get('Content-Disposition'));
        static::assertStringContainsString($asciiName, $header);

        $response->sendContent();
        $this->expectOutputString($fileData['originalName']);
    }

    public function testSlashFilename(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = static::getContainer()->get('import_export_file.repository');

        $nameWithSlash = 'Name with /\/\/\ slashes';

        $fileData = [
            'id' => Uuid::randomHex(),
            'originalName' => $nameWithSlash,
            'path' => 'test\/.csv',
            'expireDate' => new \DateTime(),
        ];
        $filesystem->write($fileData['path'], $fileData['originalName']);
        $context = Context::createDefaultContext();
        $fileRepository->create([$fileData], $context);

        $downloadService = $this->createDownloadService($filesystem, $fileRepository);
        $accessToken = $downloadService->regenerateToken($context, $fileData['id']);

        $response = $downloadService->createFileResponse($context, $fileData['id'], $accessToken);
        static::assertIsString($header = $response->headers->get('Content-Disposition'));
        static::assertStringNotContainsString($nameWithSlash, $header);
        static::assertStringContainsString('Name with  slashes', $header);
    }

    public function testDownloadWithInvalidAccessToken(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = static::getContainer()->get('import_export_file.repository');

        $asciiName = 'Name with non-ascii chars';

        $fileData = [
            'id' => Uuid::randomHex(),
            'originalName' => $asciiName . ' öäüß',
            'path' => 'test.csv',
            'expireDate' => new \DateTime(),
            'accessToken' => 'token',
        ];
        $filesystem->write($fileData['path'], $fileData['originalName']);
        $context = Context::createDefaultContext();
        $fileRepository->create([$fileData], $context);

        $downloadService = $this->createDownloadService($filesystem, $fileRepository);

        $this->expectException(InvalidFileAccessTokenException::class);

        $downloadService->createFileResponse($context, $fileData['id'], 'token');
    }

    public function testDownloadWithExpiredAccessToken(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = static::getContainer()->get('import_export_file.repository');

        $asciiName = 'Name with non-ascii chars';

        $fileData = [
            'id' => Uuid::randomHex(),
            'originalName' => $asciiName . ' öäüß',
            'path' => 'test.csv',
            'expireDate' => new \DateTime(),
            'accessToken' => 'token',
        ];
        $filesystem->write($fileData['path'], $fileData['originalName']);
        $context = Context::createDefaultContext();
        $fileRepository->create([$fileData], $context);

        $downloadService = $this->createDownloadService($filesystem, $fileRepository);

        $validToken = $downloadService->regenerateToken($context, $fileData['id']);

        // Expire it
        $connection = static::getContainer()->get(Connection::class);
        $connection->update(
            'import_export_file',
            [
                'updated_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT, strtotime('-6minutes')),
            ],
            [
                'id' => Uuid::fromHexToBytes($fileData['id']),
            ]
        );

        $this->expectException(InvalidFileAccessTokenException::class);

        $downloadService->createFileResponse($context, $fileData['id'], $validToken);
    }

    /**
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    private function createDownloadService(Filesystem $fileSystem, EntityRepository $fileRepository): DownloadService
    {
        return new DownloadService(
            $fileSystem,
            $fileRepository,
            $this->createMock(LoggerInterface::class),
            self::DEFAULT_STRATEGY,
            '',
            new NativeClock(),
        );
    }
}
