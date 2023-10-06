<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\App\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\App\Template\IconTemplateLoader;

/**
 * @internal
 */
class IconTemplateLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractTemplateLoader $templateLoader;

    protected function setUp(): void
    {
        $this->templateLoader = $this->getContainer()->get(TemplateLoader::class);
    }

    public function testDecorationWorks(): void
    {
        static::assertInstanceOf(IconTemplateLoader::class, $this->templateLoader);
    }

    public function testGetTemplatePathsForAppReturnsIconPaths(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../../Theme/fixtures/Apps/theme/manifest.xml');

        $templates = $this->templateLoader->getTemplatePathsForApp($manifest);
        sort($templates);

        static::assertEquals(
            ['app/storefront/src/assets/icon-pack/custom-icons/activity.svg', 'storefront/layout/header/logo.html.twig'],
            $templates
        );
    }

    public function testGetTemplateContentForAppReturnsIconPaths(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../../Theme/fixtures/Apps/theme/manifest.xml');

        static::assertStringEqualsFile(
            __DIR__ . '/../../../Theme/fixtures/Apps/theme/Resources/app/storefront/src/assets/icon-pack/custom-icons/activity.svg',
            $this->templateLoader->getTemplateContent('app/storefront/src/assets/icon-pack/custom-icons/activity.svg', $manifest)
        );
    }
}
