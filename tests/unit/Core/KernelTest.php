<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    private const PROJECT_WITH_XML_PACKAGES = __DIR__ . '/_fixtures/Kernel/project-with-xml-packages';

    private const PROJECT_WITH_XML_SERVICES = __DIR__ . '/_fixtures/Kernel/project-with-xml-services';

    private const PROJECT_WITH_ENV_XML_SERVICES = __DIR__ . '/_fixtures/Kernel/project-with-env-xml-services';

    private const PROJECT_WITH_XML_ROUTES = __DIR__ . '/_fixtures/Kernel/project-with-xml-routes';

    private const PROJECT_WITH_XML_ROUTES_DIR = __DIR__ . '/_fixtures/Kernel/project-with-xml-routes-dir';

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

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureContainerTriggersDeprecationForXmlPackageConfiguration(): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the package configuration to YAML or PHP format.',
            self::PROJECT_WITH_XML_PACKAGES . '/config/packages/unit_test.xml',
        )));

        $this->captureContainerLoads('fooBar', self::PROJECT_WITH_XML_PACKAGES);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureContainerTriggersDeprecationForXmlServiceDefinitions(): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the service definitions to PHP format (services.php).',
            self::PROJECT_WITH_XML_SERVICES . '/config/services.xml',
        )));

        $this->captureContainerLoads('fooBar', self::PROJECT_WITH_XML_SERVICES);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureContainerTriggersDeprecationForEnvironmentSpecificXmlServiceDefinitions(): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the service definitions to PHP format (services_test.php).',
            self::PROJECT_WITH_ENV_XML_SERVICES . '/config/services_test.xml',
        )));

        $this->captureContainerLoads('test', self::PROJECT_WITH_ENV_XML_SERVICES);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigureContainerStillLoadsConfigGlobsWhenDeprecationsAreDisabled(): void
    {
        $confDir = self::PROJECT_WITH_XML_SERVICES . '/config';

        $captured = $this->captureContainerLoads('fooBar', self::PROJECT_WITH_XML_SERVICES);

        static::assertContains([$confDir . '/{services}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureRoutesTriggersDeprecationForXmlRouteDefinitions(): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (routes.php).',
            self::PROJECT_WITH_XML_ROUTES . '/config/routes.xml',
        )));

        $this->captureRouteImports('test', self::PROJECT_WITH_XML_ROUTES);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureRoutesTriggersDeprecationForXmlFilesInRoutesDirectory(): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in the project configuration directory is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (nested.php).',
            self::PROJECT_WITH_XML_ROUTES_DIR . '/config/routes/nested.xml',
        )));

        $this->captureRouteImports('test', self::PROJECT_WITH_XML_ROUTES_DIR);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigureRoutesStillReachesRouteImportWhenDeprecationsAreDisabled(): void
    {
        $confDir = self::PROJECT_WITH_XML_ROUTES . '/config';

        $captured = $this->captureRouteImports('test', self::PROJECT_WITH_XML_ROUTES);

        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    private function createKernel(string $environment = 'fooBar', string $projectDir = self::PROJECT_DIR): KernelStub
    {
        return new KernelStub(
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
    private function captureContainerLoads(string $environment, string $projectDir): array
    {
        $captured = [];
        $loader = static::createStub(LoaderInterface::class);
        $loader->method('load')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): mixed {
                $captured[] = [$resource, $type];

                return null;
            }
        );

        $this->createKernel($environment, $projectDir)->runConfigureContainer(new ContainerBuilder(), $loader);

        return $captured;
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureRouteImports(string $environment, string $projectDir = self::PROJECT_DIR): array
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
        $this->createKernel($environment, $projectDir)->loadRoutes($loader);

        return $captured;
    }
}

/**
 * Exposes the protected `configureContainer()`, which has no public entry point short of a real kernel boot.
 * Route configuration needs no stub: it is reachable through the public `loadRoutes()`.
 *
 * @internal
 */
class KernelStub extends Kernel
{
    public function runConfigureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $this->configureContainer($container, $loader);
    }
}
