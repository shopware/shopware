<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
#[CoversClass(PluginZipDetector::class)]
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
        $this->expectException(PluginException::class);
        ZipUtils::openZip(__DIR__ . '/_fixture/archives/NoZip.zip');
    }

    public function testDetectThrowsExceptionWhenNoPluginInZip(): void
    {
        $this->expectException(PluginException::class);
        $this->zipDetector->detect(__DIR__ . '/_fixture/archives/NoPlugin.zip');
    }

    #[DataProvider('archiveProvider')]
    public function testDetect(string $archivePath, string $expectedType): void
    {
        static::assertEquals(
            $expectedType,
            $this->zipDetector->detect($archivePath),
        );
    }

    /**
     * @return array<array{0: string, 1:string}>
     */
    public static function archiveProvider(): array
    {
        return [
            [__DIR__ . '/_fixture/archives/SwagFashionTheme.zip', 'plugin'],
            [__DIR__ . '/_fixture/archives/App.zip', 'app'],
        ];
    }
}
