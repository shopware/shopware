<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
class PluginZipDetectorTest extends TestCase
{
    private PluginZipDetector $zipDetector;

    protected function setUp(): void
    {
        $this->zipDetector = new PluginZipDetector();
    }

    public function testIsPlugin(): void
    {
        $archive = ZipUtils::openZip(__DIR__ . '/_fixture/archives/SwagFashionTheme.zip');

        $isPlugin = $this->zipDetector->isPlugin($archive);

        static::assertTrue($isPlugin);
    }

    public function testIsNoPlugin(): void
    {
        $archive = ZipUtils::openZip(__DIR__ . '/_fixture/archives/NoPlugin.zip');

        $isPlugin = $this->zipDetector->isPlugin($archive);

        static::assertFalse($isPlugin);
    }

    public function testThrowsExceptionWithNoZip(): void
    {
        $this->expectException(PluginExtractionException::class);
        ZipUtils::openZip(__DIR__ . '/_fixture/archives/NoZip.zip');
    }
}
