<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Service\DownloadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class DownloadServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUtf8Filename(): void
    {
        $filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $fileRepository = $this->getContainer()->get('import_export_file.repository');

        $asciiName = 'Name with non-ascii chars';

        $fileData = [
            'id' => Uuid::randomHex(),
            'originalName' => $asciiName . ' öäüß',
            'path' => 'test.csv',
            'expireDate' => new \DateTime(),
            'accessToken' => 'token',
        ];
        $filesystem->put($fileData['path'], $fileData['originalName']);
        $context = Context::createDefaultContext();
        $fileRepository->create([$fileData], $context);

        $downloadService = new DownloadService($filesystem, $fileRepository);

        $response = $downloadService->createFileResponse($context, $fileData['id'], 'token');
        static::assertStringContainsString($asciiName, $response->headers->get('Content-Disposition'));

        $response->sendContent();
        $this->expectOutputString($fileData['originalName']);
    }
}
