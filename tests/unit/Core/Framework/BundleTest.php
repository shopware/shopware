<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Bundle::class)]
class BundleTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/_fixtures/BundleTest';

    public function testConfigureRoutesImportsAllRoutePatternsWhenResourcesConfigExists(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-config';
        $confDir = $bundlePath . '/Resources/config';

        $captured = $this->captureRouteImports($bundlePath, 'test');

        static::assertContains([$confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
        static::assertContains([$confDir . '/{routes}_test' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    public function testConfigureRoutesImportsNothingWhenResourcesConfigMissing(): void
    {
        $captured = $this->captureRouteImports(self::FIXTURES_DIR . '/without-config', 'test');

        static::assertSame([], $captured);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testBuildTriggersDeprecationForXmlServiceDefinitions(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-xml-services';

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in bundle "BundleStub" is deprecated and will not be loaded in v6.8.0.0. Migrate the service definitions to PHP format (services.php).',
            $bundlePath . '/Resources/config/services.xml',
        )));

        (new BundleStub($bundlePath))->build($this->createContainerBuilder());
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildStillLoadsXmlServiceDefinitionsWhenDeprecationsAreDisabled(): void
    {
        $container = $this->createContainerBuilder();

        (new BundleStub(self::FIXTURES_DIR . '/with-xml-services'))->build($container);

        static::assertTrue($container->hasDefinition('unit_test.bundle.xml_service'));
    }

    public function testBuildLoadsPhpServiceDefinitionsWithoutDeprecation(): void
    {
        $container = $this->createContainerBuilder();

        (new BundleStub(self::FIXTURES_DIR . '/with-php-services'))->build($container);

        static::assertTrue($container->hasDefinition('unit_test.bundle.php_service'));
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DataProvider('xmlRouteDefinitionCases')]
    public function testConfigureRoutesTriggersDeprecationForXmlRouteDefinitions(
        string $fixture,
        string $xmlFile,
        string $phpFile,
    ): void {
        $bundlePath = self::FIXTURES_DIR . '/' . $fixture;

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in bundle "BundleStub" is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (%s).',
            $bundlePath . '/Resources/config/' . $xmlFile,
            $phpFile,
        )));

        $this->captureRouteImports($bundlePath, 'test');
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function xmlRouteDefinitionCases(): iterable
    {
        yield 'routes.xml in the config directory' => ['with-xml-routes', 'routes.xml', 'routes.php'];
        yield 'xml file nested in the routes directory' => ['with-xml-routes-dir', 'routes/nested.xml', 'nested.php'];
        yield 'environment-specific routes_test.xml' => ['with-xml-env-routes', 'routes_test.xml', 'routes_test.php'];
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigureRoutesStillReachesRouteImportWhenDeprecationsAreDisabled(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-xml-routes';
        $confDir = $bundlePath . '/Resources/config';

        $captured = $this->captureRouteImports($bundlePath, 'test');

        static::assertContains([$confDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob'], $captured);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testConfigureRouteOverwritesTriggersDeprecationForXmlRouteDefinitions(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-xml-routes';

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in bundle "BundleStub" is deprecated and will not be loaded in v6.8.0.0. Migrate the route definitions to PHP format (routes_overwrite.php).',
            $bundlePath . '/Resources/config/routes_overwrite.xml',
        )));

        (new BundleStub($bundlePath))->configureRouteOverwrites(
            new RoutingConfigurator(new RouteCollection(), static::createStub(PhpFileLoader::class), '/tmp', '/tmp'),
            'test',
        );
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    public function testBuildDefaultConfigTriggersDeprecationForXmlPackageConfiguration(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-xml-packages';

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: The XML configuration file "%s" in bundle "BundleStub" is deprecated and will not be loaded in v6.8.0.0. Migrate the package configuration to YAML or PHP format.',
            $bundlePath . '/Resources/config/packages/unit_test.xml',
        )));

        (new BundleStub($bundlePath))->runBuildDefaultConfig($this->createContainerBuilder());
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the XML configuration deprecation triggers
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildDefaultConfigStillLoadsXmlPackageConfigurationWhenDeprecationsAreDisabled(): void
    {
        $container = $this->createContainerBuilder();

        (new BundleStub(self::FIXTURES_DIR . '/with-xml-packages'))->runBuildDefaultConfig($container);

        static::assertSame('loaded', $container->getParameter('unit_test.bundle.xml_package'));
    }

    public function testGetTwigComponentNamespace(): void
    {
        $bundleClass = new class extends Bundle {};

        static::assertSame(
            $bundleClass::getTwigComponentNamespace(),
            $bundleClass->getNamespace() . '\\Resources\\views\\components\\'
        );
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        return $container;
    }

    /**
     * @return list<array{0: mixed, 1: ?string}>
     */
    private function captureRouteImports(string $bundlePath, string $environment): array
    {
        $captured = [];
        $loader = static::createStub(PhpFileLoader::class);
        $loader->method('import')->willReturnCallback(
            function (mixed $resource, ?string $type = null) use (&$captured): array {
                $captured[] = [$resource, $type];

                return [];
            }
        );

        (new BundleStub($bundlePath))->configureRoutes(
            new RoutingConfigurator(new RouteCollection(), $loader, '/tmp', '/tmp'),
            $environment,
        );

        return $captured;
    }
}

/**
 * @internal
 */
class BundleStub extends Bundle
{
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function runBuildDefaultConfig(ContainerBuilder $container): void
    {
        $this->buildDefaultConfig($container);
    }
}
