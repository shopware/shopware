<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Script\ScriptLoader;
use Shopware\Core\Framework\App\Script\ScriptLoaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ScriptLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ScriptLoaderInterface $scriptLoader;

    public function setUp(): void
    {
        $this->scriptLoader = $this->getContainer()->get(ScriptLoader::class);
    }

    public function testGetTemplatePathsForApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $scripts = $this->scriptLoader->getScriptPathsForAppPath($manifest->getPath());

        static::assertEquals(
            ['product-page-loaded/product-page-script.twig'],
            $scripts
        );
    }

    public function testGetTemplatePathsForAppWhenScriptDirDoesntExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');

        static::assertEquals(
            [],
            $this->scriptLoader->getScriptPathsForAppPath($manifest->getPath())
        );
    }

    public function testGetTemplateContent(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/scripts/product-page-loaded/product-page-script.twig',
            $this->scriptLoader->getScriptContent('product-page-loaded/product-page-script.twig', $manifest->getPath())
        );
    }

    public function testGetTemplateContentThrowsOnNotFoundFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::expectException(\RuntimeException::class);
        $this->scriptLoader->getScriptContent('does/not/exist', $manifest->getPath());
    }

    public function testGetLastModifiedDate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::assertEquals(
            (new \DateTimeImmutable())->setTimestamp(
                filemtime(__DIR__ . '/../Manifest/_fixtures/test/Resources/scripts/product-page-loaded/product-page-script.twig')
            ),
            $this->scriptLoader->getLastModifiedDate('product-page-loaded/product-page-script.twig', $manifest->getPath())
        );
    }

    public function testGetLastModifiedDateThrowsOnNotFoundFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::expectException(\RuntimeException::class);
        $this->scriptLoader->getLastModifiedDate('does/not/exist', $manifest->getPath());
    }
}
