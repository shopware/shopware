<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Twig\InheritanceExtension;
use Shopware\Core\Framework\Twig\TemplateFinder;
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
        $this->cacheDir = $this->getKernel()->getCacheDir() . '/twig_test_' . time();
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
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => $this->cache,
        ]);

        $templateFinder = new TemplateFinder($loader, $this->getContainer()->get('kernel'));
        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();

        $templatePath = $templateFinder->find('frontend/base.html.twig');
        $template = $twig->loadTemplate($templatePath);
        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));
    }

    public function testMultipleInheritanceWithChangingTemplateChain(): void
    {
        static::markTestSkipped('Twig cache is not invalidated');

        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => $this->cache,
        ]);

        $bundlePlugin1 = new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1');
        $bundlePlugin2 = new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2');

        $templateFinder = new TemplateFinder($loader, $this->getContainer()->get('kernel'));
        $templateFinder->addBundle($bundlePlugin1);
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();

        $templatePath = $templateFinder->find('frontend/base.html.twig');
        $template = $twig->loadTemplate($templatePath);
        static::assertSame('Base/TestPlugin1/TestPlugin2', $template->render([]));

        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');
        $loader->addPath(__DIR__ . '/fixtures/Storefront/Resources/views', 'Storefront');
        $twig = new Environment($loader, [
            'cache' => $this->cache,
        ]);

        $templateFinder = new TemplateFinder($loader, $this->getContainer()->get('kernel'));
        $templateFinder->addBundle($bundlePlugin2);
        $twig->addExtension(new InheritanceExtension($templateFinder));
        $twig->getExtension(InheritanceExtension::class)->getFinder();

        $templatePath = $templateFinder->find('frontend/base.html.twig');
        $template = $twig->loadTemplate($templatePath);
        static::assertSame('Base/TestPlugin2', $template->render([]));
    }
}
