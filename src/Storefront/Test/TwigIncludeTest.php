<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\Framework\Twig\InheritanceExtension;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Storefront\Test\fixtures\BundleFixture;
use Twig_Environment;
use Twig_Loader_Filesystem;

class TwigIncludeTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

    public function setUp()
    {
    }

    /**
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function testMultipleInheritance()
    {
        $loader = new Twig_Loader_Filesystem(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Twig_Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($loader);
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1', 'TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2', 'TestPlugin1');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/index.html.twig');
        $this->assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }
}
