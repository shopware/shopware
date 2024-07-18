<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\ThemeFileImporter;

/**
 * @internal
 */
#[CoversClass(ThemeFileImporter::class)]
class ThemeFileImporterTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = __DIR__ . '/fixtures/ThemeFileImporter';
    }

    public function testFileExists(): void
    {
        $existingFile = realpath(__DIR__ . '/fixtures/SimpleTheme/Resources/theme.json');

        $importer = new ThemeFileImporter($this->projectDir);
        static::assertFalse($importer->fileExists('random-file.twig'));
        static::assertIsString($existingFile);
        static::assertTrue($importer->fileExists($existingFile));
    }

    public function testFileRealPath(): void
    {
        $existingFile = realpath(__DIR__ . '/fixtures/SimpleTheme/Resources/theme.json');

        $importer = new ThemeFileImporter($this->projectDir);
        static::assertSame('random-file.twig', $importer->getRealPath('random-file.twig'));
        static::assertIsString($existingFile);
        static::assertSame($existingFile, $importer->getRealPath($existingFile));
        static::assertSame($existingFile, $importer->getRealPath($this->stripProjectDir($existingFile)));
    }

    private function stripProjectDir(string $path): string
    {
        if (str_starts_with($path, $this->projectDir)) {
            return substr($path, \strlen($this->projectDir) + 1);
        }

        return $path;
    }
}
