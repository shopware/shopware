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
        $download = new Download();

        $tempfile = tempnam('/tmp', 'updateFile');

        $download->downloadFile(
            'http://assets.shopware.com/sw_logo_white.png',
            $tempfile,
            10521,
            '5f98432a760cae72c85b1835017306bdd84e2f68'
        );

        static::assertFileExists($tempfile);
        static::assertEquals(filesize(__DIR__ . '/../_fixtures/sw_logo_white.png'), filesize($tempfile));

        unlink($tempfile);
    }
}
