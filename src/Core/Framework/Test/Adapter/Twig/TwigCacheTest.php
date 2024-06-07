<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Twig\Cache\CacheInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Group('cache')]
class TwigCacheTest extends TestCase
{
    use KernelTestBehaviour;

    public function testChangeCacheOnDifferentPlugins(): void
    {
        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templateName = 'storefront/frontend/index.html.twig';

        $templateFinder->find($templateName);

        $cache = $twig->getCache(false);
        static::assertInstanceOf(CacheInterface::class, $cache);
        $firstCacheKey = $cache->generateKey($templateName, static::class);

        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('Storefront', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $templateFinder->find($templateName);
        $cache = $twig->getCache(false);
        static::assertInstanceOf(CacheInterface::class, $cache);
        $secondCacheKey = $cache->generateKey($templateName, static::class);

        static::assertNotEquals($firstCacheKey, $secondCacheKey);
    }

    /**
     * @param BundleFixture[] $bundles
     *
     * @return array{0: Environment, 1: TemplateFinder}
     */
    private function createFinder(array $bundles): array
    {
        $twig = $this->getContainer()->get('twig');

        $loader = $this->getContainer()->get('twig.loader.native_filesystem');
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->expects(static::any())
            ->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            $this->getKernel()->getCacheDir(),
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    $this->getContainer()->get(Connection::class)
                ),
            ]),
            $scopeDetector,
        );

        return [$twig, $templateFinder];
    }
}
