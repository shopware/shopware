<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
class TwigSwExtendsTest extends TestCase
{
    use KernelTestBehaviour;

    private string $cacheDir;

    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->cacheDir = $this->getKernel()->getCacheDir() . '/twig_test_' . microtime();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $filesystem = $this->getContainer()->get(Filesystem::class);
        $filesystem->remove($this->cacheDir);
    }

    public function testMultipleInheritance(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('@Storefront/storefront/frontend/base.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));
    }

    public function testMultipleInheritanceIfExtendingTemplateInSamePlugin(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('@Storefront/storefront/frontend/extend_template_in_same_plugin.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        static::assertSame('Base/TestPlugin1/TestPlugin2/TestPlugin2Content', $template->render([]));
    }

    public function testMultipleInheritanceIfExtendingBaseTemplateInSamePlugin(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('@Storefront/storefront/frontend/extend_base_template_in_same_plugin.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        static::assertSame('Base/TestPlugin1/TestPlugin2/StorefrontContent/TestPlugin2Content', $template->render([]));
    }

    public function testPluginExtendsOtherPluginsNewTemplate(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
        ]);

        $templatePath = $templateFinder->find('@TestPlugin1/storefront/frontend/controller/index.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);
        static::assertSame('TestPlugin1/TestPlugin2', $template->render([]));
    }

    public function testPluginExtendsOtherPluginsNewTemplateDifferentSorted(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('@TestPlugin1/storefront/frontend/controller/index.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);
        static::assertSame('TestPlugin1/TestPlugin2', $template->render([]));
    }

    public function testPluginExtendsOtherPluginsNewTemplateDifferentFirstStorefront(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
        ]);

        $templatePath = $templateFinder->find('@TestPlugin1/storefront/frontend/controller/index.html.twig');

        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);
        static::assertSame('TestPlugin1/TestPlugin2', $template->render([]));
    }

    public function testMultipleInheritanceWithChangingTemplateChain(): void
    {
        static::markTestSkipped('Twig cache is not invalidated');

        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('storefront/frontend/base.html.twig');
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);
        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));

        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('storefront/frontend/base.html.twig');
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);
        static::assertSame('Base/TestPlugin2', $template->render([]));
    }

    public function testPluginExtendsOtherPlugin(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('@TestPlugin1/storefront/frontend/new_template.html.twig');
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        $template->render([]);
        static::assertSame('AnotherBaseTestPlugin1TestPlugin2', $template->render([]));
    }

    public function testExtendWithLoop(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        // order is  important for this test. 2 needs to be loaded before 1
        $templatePath = $templateFinder->find('@Storefront/storefront/frontend/testExtendWithLoop/loop.html.twig');
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        $template->render([]);
        static::assertSame(
            '-s21-s21-s21-s21-s21-s21',
            $template->render([])
        );
    }

    private function createFinder(array $bundles): array
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        /** @var BundleFixture $bundle */
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new Environment($loader, ['cache' => $this->cache]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            $this->cacheDir,
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    $this->getContainer()->get(Connection::class)
                ),
            ])
        );

        $twig->addExtension(new NodeExtension($templateFinder));
        $twig->getExtension(NodeExtension::class)->getFinder();

        return [$twig, $templateFinder];
    }
}
