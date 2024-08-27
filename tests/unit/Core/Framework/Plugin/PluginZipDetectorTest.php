<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

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

    private string $fixturePath;

    protected function setUp(): void
    {
        $this->zipDetector = new PluginZipDetector();
        $this->fixturePath = __DIR__ . '/_fixtures/archives/';
    }

    public function testIsPlugin(): void
    {
        $archive = ZipUtils::openZip($this->fixturePath . 'SwagFashionTheme.zip');

        $isPlugin = $this->zipDetector->isPlugin($archive);

        static::assertTrue($isPlugin);
    }

    public function testIsNoPlugin(): void
    {
        $archive = ZipUtils::openZip($this->fixturePath . 'NoPlugin.zip');

        $isPlugin = $this->zipDetector->isPlugin($archive);

        static::assertFalse($isPlugin);
    }

    public function testThrowsExceptionWithNoZip(): void
    {
        $this->expectException(PluginException::class);
        ZipUtils::openZip($this->fixturePath . 'NoZip.zip');
    }

    public function testDetectThrowsExceptionWhenNoPluginInZip(): void
    {
        $this->expectException(PluginException::class);
        $this->zipDetector->detect($this->fixturePath . 'NoPlugin.zip');
    }

    #[DataProvider('archiveProvider')]
    public function testDetect(string $archiveName, string $expectedType): void
    {
        static::assertEquals(
            $expectedType,
            $this->zipDetector->detect($this->fixturePath . $archiveName),
        );
    }

    /**
     * @return array<array{0: string, 1:string}>
     */
    public static function archiveProvider(): array
    {
        return [
            ['SwagFashionTheme.zip', 'plugin'],
            ['App.zip', 'app'],
        ];
    }
}
