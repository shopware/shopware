<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Update\Services\Download;

class DownloadTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDownloadFile(): void
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        copy(__DIR__ . '/../_fixtures/test.zip', $projectDir . '/public/test.zip');

        $download = new Download();

        $tempfile = tempnam('/tmp', 'updateFile');

        $appUrl = getenv('APP_URL');
        $download->downloadFile(
            $appUrl . '/test.zip',
            $tempfile,
            201,
            '424b743e97730d95de7a3fb75d690820ff7bac6d'
        );

        static::assertFileExists($tempfile);
        static::assertEquals(filesize(__DIR__ . '/../_fixtures/test.zip'), filesize($tempfile));

        unlink($tempfile);
        unlink($projectDir . '/public/test.zip');
    }
}
