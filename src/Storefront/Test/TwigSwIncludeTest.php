<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\InheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Storefront\Test\fixtures\BundleFixture;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigSwIncludeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMultipleInheritance(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/index.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeWithVars(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/withvars.html.twig');
        static::assertSame('innerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeWithVarsOnly(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/withvarsonly.html.twig');
        static::assertSame('innerblockvaluefromindexnotvisibleinnerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeTemplatenameExpression(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/templatenameexpression.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeIgnoreMissing(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/notemplatefound.html.twig');
        static::assertSame('nothingelse', $template->render([]));
    }

    public function testDynamicInclude(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/dynamic_include.html.twig');
        static::assertSame('a', $template->render(['child' => 'a']));
        static::assertSame('b', $template->render(['child' => 'b']));
    }

    public function testDynamicIncludeExtended(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate('storefront/frontend/dynamic_include.html.twig');
        static::assertSame('a/TestPlugin1_a/TestPlugin2_a', $template->render(['child' => 'a']));
        static::assertSame('b/TestPlugin1_b/TestPlugin2_b', $template->render(['child' => 'b']));
    }

    private function initTwig(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        $twig = new Environment($loader, ['cache' => false]);

        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->getParameter('kernel.cache_dir') . '/' . microtime());

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $templateFinder->registerBundles($kernel);

        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();

        return $twig;
    }
}
