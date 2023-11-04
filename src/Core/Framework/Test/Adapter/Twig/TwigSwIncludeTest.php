<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
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

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/index.html.twig'), 'storefront/frontend/index.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testInterpolatedInheritance(): void
    {
        // order storefront, TestPlugin2, TestPlugin1 is important for this test. Do not change.
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('@TestPlugin1/storefront/frontend/include_base.html.twig'), '@TestPlugin1/storefront/frontend/include_base.html.twig');
        static::assertSame('--(Textfield base)(Textfield Extend)-(Textfield base)(Textfield Extend)--', $template->render([]));
    }

    public function testIncludeWithVars(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/withvars.html.twig'), 'storefront/frontend/withvars.html.twig');
        static::assertSame('innerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeWithVarsOnly(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/withvarsonly.html.twig'), 'storefront/frontend/withvarsonly.html.twig');
        static::assertSame('innerblockvaluefromindexnotvisibleinnerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeTemplatenameExpression(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/templatenameexpression.html.twig'), 'storefront/frontend/templatenameexpression.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeIgnoreMissing(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/notemplatefound.html.twig'), 'storefront/frontend/notemplatefound.html.twig');
        static::assertSame('nothingelse', $template->render([]));
    }

    public function testDynamicInclude(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/dynamic_include.html.twig'), 'storefront/frontend/dynamic_include.html.twig');
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

        $template = $twig->loadTemplate($twig->getTemplateClass('storefront/frontend/dynamic_include.html.twig'), 'storefront/frontend/dynamic_include.html.twig');
        static::assertSame('a/TestPlugin1_a/TestPlugin2_a', $template->render(['child' => 'a']));
        static::assertSame('b/TestPlugin1_b/TestPlugin2_b', $template->render(['child' => 'b']));
    }

    private function initTwig(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        /** @var BundleFixture $bundle */
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new TwigEnvironment($loader, ['cache' => false]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            $this->getContainer()->getParameter('kernel.cache_dir') . '/' . microtime(),
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    $this->getContainer()->get(Connection::class)
                ),
            ])
        );

        $twig->addExtension(new NodeExtension($templateFinder));
        $twig->getExtension(NodeExtension::class)->getFinder();

        return $twig;
    }
}
