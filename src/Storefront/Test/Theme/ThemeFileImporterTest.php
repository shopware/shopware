<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\ThemeFileImporter;

/**
 * @internal
 */
class ThemeFileImporterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testFileExists(): void
    {
        $existingFile = realpath(__DIR__ . '/fixtures/SimpleTheme/Resources/theme.json');

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $importer = new ThemeFileImporter($projectDir);
        static::assertFalse($importer->fileExists('random-file.twig'));
        static::assertTrue($importer->fileExists($existingFile));
    }

    public function testFileRealPath(): void
    {
        $existingFile = realpath(__DIR__ . '/fixtures/SimpleTheme/Resources/theme.json');

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $importer = new ThemeFileImporter($projectDir);
        static::assertSame('random-file.twig', $importer->getRealPath('random-file.twig'));
        static::assertSame($existingFile, $importer->getRealPath($existingFile));
        static::assertSame($existingFile, $importer->getRealPath($this->stripProjectDir($existingFile)));
    }

    private function stripProjectDir(string $path): string
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        if (str_starts_with($path, $projectDir)) {
            return substr($path, \strlen($projectDir) + 1);
        }

        return $path;
    }
}
