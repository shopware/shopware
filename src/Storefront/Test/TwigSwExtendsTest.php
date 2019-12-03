<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\InheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Storefront\Test\fixtures\BundleFixture;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Cache\CacheInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigSwExtendsTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function setUp(): void
    {
        $this->cacheDir = $this->getKernel()->getCacheDir() . '/twig_test_' . microtime();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    public function tearDown(): void
    {
        /** @var Filesystem $filesystem */
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

        $template = $twig->loadTemplate($templatePath);

        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));
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
        $template = $twig->loadTemplate($templatePath);
        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));

        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templatePath = $templateFinder->find('storefront/frontend/base.html.twig');
        $template = $twig->loadTemplate($templatePath);
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
        $template = $twig->loadTemplate($templatePath);

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
        $template = $twig->loadTemplate($templatePath);

        $template->render([]);
        static::assertSame(
            '-s21-s21-s21-s21-s21-s21',
            $template->render([])
        );
    }

    private function createFinder(array $bundles): array
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        $twig = new Environment($loader, ['cache' => $this->cache]);

        $templateFinder = new TemplateFinder($twig, $loader, $this->cacheDir);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $templateFinder->registerBundles($kernel);

        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();

        return [$twig, $templateFinder];
    }
}
