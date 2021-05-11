<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TemplateLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractTemplateLoader $templateLoader;

    public function setUp(): void
    {
        $this->templateLoader = $this->getContainer()->get(TemplateLoader::class);
    }

    public function testGetTemplatePathsForApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $templates = $this->templateLoader->getTemplatePathsForApp($manifest);
        sort($templates);

        static::assertEquals(
            ['storefront/layout/header/header.html.twig', 'storefront/layout/header/logo.html.twig'],
            $templates
        );
    }

    public function testGetTemplatePathsForAppWhenViewDirDoesntExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');

        static::assertEquals(
            [],
            $this->templateLoader->getTemplatePathsForApp($manifest)
        );
    }

    public function testGetTemplateContent(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/logo.html.twig',
            $this->templateLoader->getTemplateContent('storefront/layout/header/logo.html.twig', $manifest)
        );
    }

    public function testGetTemplateContentThrowsOnNotFoundFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::expectException(\RuntimeException::class);
        $this->templateLoader->getTemplateContent('does/not/exist', $manifest);
    }
}
