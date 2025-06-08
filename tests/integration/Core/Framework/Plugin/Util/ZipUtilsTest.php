<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
class ZipUtilsTest extends TestCase
{
    public function testExceptionIsThrownIfZipFileDoesNotExist(): void
    {
        static::expectException(PluginException::class);
        static::expectExceptionMessage('No such zip file: /some/file/that/does/not/exist.zip');

        ZipUtils::openZip('/some/file/that/does/not/exist.zip');
    }

    public function testExceptionIsThrownIfZipIsInvalid(): void
    {
        static::expectException(PluginException::class);
        static::expectExceptionMessage(\sprintf('%s is not a zip archive.', __FILE__));

        ZipUtils::openZip(__FILE__);
    }

    public function testArchiveIsReturnedForValidZip(): void
    {
        $archive = ZipUtils::openZip(
            __DIR__ . '/../../../../../../src/Core/Framework/Test/Plugin/_fixture/archives/App.zip'
        );

        try {
            static::assertSame(20, $archive->numFiles);
        } finally {
            $archive->close();
        }
    }
}
