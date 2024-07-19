<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\App\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Storefront\Framework\App\Template\IconTemplateLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;

/**
 * @internal
 */
#[CoversClass(IconTemplateLoader::class)]
class IconTemplateLoaderTest extends TestCase
{
    private IconTemplateLoader $templateLoader;

    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../../../Theme/fixtures/Apps/theme/manifest.xml');

        $sourceResolver = new StaticSourceResolver([
            'SwagTheme' => new Filesystem(__DIR__ . '/../../../Theme/fixtures/Apps/theme'),
        ]);

        $this->templateLoader = new IconTemplateLoader(
            new TemplateLoader($sourceResolver),
            new StorefrontPluginConfigurationFactory(
                __DIR__,
                $this->createMock(KernelPluginLoader::class),
                $sourceResolver
            ),
            $sourceResolver,
        );
    }

    public function testGetTemplatePathsForAppReturnsIconPaths(): void
    {
        $templates = $this->templateLoader->getTemplatePathsForApp($this->manifest);
        \sort($templates);

        static::assertEquals(
            ['app/storefront/src/assets/icon-pack/custom-icons/activity.svg', 'storefront/layout/header/logo.html.twig'],
            $templates
        );
    }

    public function testGetTemplateContentForAppReturnsIconPaths(): void
    {
        static::assertStringEqualsFile(
            __DIR__ . '/../../../Theme/fixtures/Apps/theme/Resources/app/storefront/src/assets/icon-pack/custom-icons/activity.svg',
            $this->templateLoader->getTemplateContent('app/storefront/src/assets/icon-pack/custom-icons/activity.svg', $this->manifest)
        );
    }
}
