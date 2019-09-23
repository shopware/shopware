<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Twig\InheritanceExtension;
use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Storefront\Test\fixtures\BundleFixture;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigSwIncludeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMultipleInheritance(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/index.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeWithVars(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/withvars.html.twig');
        static::assertSame('innerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeWithVarsOnly(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/withvarsonly.html.twig');
        static::assertSame('innerblockvaluefromindexnotvisibleinnerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeTemplatenameExpression(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();
        $template = $twig->loadTemplate('frontend/templatenameexpression.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeIgnoreMissing(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();
        $template = $twig->loadTemplate('frontend/notemplatefound.html.twig');
        static::assertSame('nothingelse', $template->render([]));
    }

    public function testDynamicInclude(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/dynamic_include.html.twig');
        static::assertSame('a', $template->render(['child' => 'a']));
        static::assertSame('b', $template->render(['child' => 'b']));
    }

    public function testDynamicIncludeExtended(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $templateFinder = new TemplateFinder($twig, $loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));

        $template = $twig->loadTemplate('frontend/dynamic_include.html.twig');
        static::assertSame('a/TestPlugin1_a/TestPlugin2_a', $template->render(['child' => 'a']));
        static::assertSame('b/TestPlugin1_b/TestPlugin2_b', $template->render(['child' => 'b']));
    }
}
