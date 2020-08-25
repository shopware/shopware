<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TemplateLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetTemplatePathsForApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $templateLoader = new TemplateLoader();

        static::assertEquals(
            ['storefront/layout/header/logo.html.twig'],
            $templateLoader->getTemplatePathsForApp($manifest)
        );
    }

    public function testGetTemplatePathsForAppWhenViewDirDoesntExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/minimal/manifest.xml');
        $templateLoader = new TemplateLoader();

        static::assertEquals(
            [],
            $templateLoader->getTemplatePathsForApp($manifest)
        );
    }

    public function testGetTemplateContent(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $templateLoader = new TemplateLoader();

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/storefront/layout/header/logo.html.twig',
            $templateLoader->getTemplateContent('storefront/layout/header/logo.html.twig', $manifest)
        );
    }

    public function testGetTemplateContentThrowsOnNotFoundFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $templateLoader = new TemplateLoader();

        static::expectException(\RuntimeException::class);
        $templateLoader->getTemplateContent('does/not/exist', $manifest);
    }
}
