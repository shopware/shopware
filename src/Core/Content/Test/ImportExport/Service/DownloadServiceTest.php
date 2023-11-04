<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileAccessTokenException;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('system-settings')]
class DownloadServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUtf8Filename(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = $this->getContainer()->get('import_export_file.repository');

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

        $downloadService = new DownloadService($filesystem, $fileRepository);
        $accessToken = $downloadService->regenerateToken($context, $fileData['id']);

        $response = $downloadService->createFileResponse($context, $fileData['id'], $accessToken);
        static::assertStringContainsString($asciiName, $response->headers->get('Content-Disposition'));

        $response->sendContent();
        $this->expectOutputString($fileData['originalName']);
    }

    public function testDownloadWithInvalidAccessToken(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = $this->getContainer()->get('import_export_file.repository');

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

        $downloadService = new DownloadService($filesystem, $fileRepository);

        static::expectException(InvalidFileAccessTokenException::class);

        $downloadService->createFileResponse($context, $fileData['id'], 'token');
    }

    public function testDownloadWithExpiredAccessToken(): void
    {
        $filesystem = $this->getPrivateFilesystem();
        $fileRepository = $this->getContainer()->get('import_export_file.repository');

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

        $downloadService = new DownloadService($filesystem, $fileRepository);

        $validToken = $downloadService->regenerateToken($context, $fileData['id']);

        // Expire it
        $connection = $this->getContainer()->get(Connection::class);
        $connection->update(
            'import_export_file',
            [
                'updated_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT, strtotime('-6minutes')),
            ],
            [
                'id' => Uuid::fromHexToBytes($fileData['id']),
            ]
        );

        static::expectException(InvalidFileAccessTokenException::class);

        $downloadService->createFileResponse($context, $fileData['id'], $validToken);
    }
}
