<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * The container dumping side of the kernel is covered by
 * \Shopware\Tests\Integration\Core\KernelTest, as it requires a real boot.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    /**
     * A path that is never touched: the tests below only compose strings from it.
     */
    private const PROJECT_DIR = '/project-dir';

    private const PROJECT_WITHOUT_TWIG_COMPONENT_BUNDLE = __DIR__ . '/_fixtures/Kernel/project-without-twig-component-bundle';

    private const PROJECT_WITH_TWIG_COMPONENT_BUNDLE = __DIR__ . '/_fixtures/Kernel/project-with-twig-component-bundle';

    public function testGetCacheDir(): void
    {
        static::assertStringStartsWith(self::PROJECT_DIR . '/var/cache/fooBar_h', $this->createKernel()->getCacheDir());
    }

    public function testRegisterBundlesAutoAddsTwigComponentBundleWhenMissingPreV68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->expectUserDeprecationMessageMatches('/TwigComponentBundle bundle should be added/');

        $kernel = $this->createKernel(projectDir: self::PROJECT_WITHOUT_TWIG_COMPONENT_BUNDLE);

        $bundles = iterator_to_array($kernel->registerBundles());

        static::assertSame([TwigComponentBundle::class], array_values(array_map(
            static fn (object $bundle): string => $bundle::class,
            $bundles
        )));
    }

    public function testRegisterBundlesDoesNotDuplicateTwigComponentBundleWhenConfiguredPreV68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $kernel = $this->createKernel(projectDir: self::PROJECT_WITH_TWIG_COMPONENT_BUNDLE);

        $bundles = iterator_to_array($kernel->registerBundles());

        static::assertSame([TwigComponentBundle::class], array_values(array_map(
            static fn (object $bundle): string => $bundle::class,
            $bundles
        )));
    }

    public function testConfigureRoutesImportsProjectRoutesScopedToEnvironment(): void
    {
        $confDir = self::PROJECT_DIR . '/config';

        $captured = $this->captureRouteImports('test');

        static::assertContains([$confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    public function testConfigureRoutesDoesNotImportForeignEnvironmentGlobs(): void
    {
        $confDir = self::PROJECT_DIR . '/config';

        $captured = $this->captureRouteImports('prod');

        static::assertContains([$confDir . '/{routes}/prod/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertNotContains([$confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    private function createKernel(string $environment = 'fooBar', string $projectDir = self::PROJECT_DIR): Kernel
    {
        return new Kernel(
            $environment,
            true,
            static::createStub(StaticKernelPluginLoader::class),
            'cacheId',
            '6.6.6',
            static::createStub(Connection::class),
            $projectDir,
        );
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureRouteImports(string $environment): array
    {
        $captured = [];
        $routeLoader = static::createStub(PhpFileLoader::class);
        $routeLoader->method('import')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): array {
                $captured[] = [$resource, $type];

                return [];
            }
        );

        $resolver = static::createStub(LoaderResolverInterface::class);
        $resolver->method('resolve')->willReturn($routeLoader);

        $loader = static::createStub(LoaderInterface::class);
        $loader->method('getResolver')->willReturn($resolver);

        // `loadRoutes()` is the public routing entry point Symfony registers as `kernel::loadRoutes`
        $this->createKernel($environment)->loadRoutes($loader);

        return $captured;
    }
}
