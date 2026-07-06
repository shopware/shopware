<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
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

    public function testBuildTriggersDeprecationForXmlServiceDefinitions(): void
    {
        $bundlePath = self::FIXTURES_DIR . '/with-xml-services';

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Loading service definitions from XML file "%s" in bundle "BundleStub" is deprecated and will be removed in v6.8.0.0. Migrate the file to PHP format (services.php).',
            $bundlePath . '/Resources/config/services.xml',
        )));

        (new BundleStub($bundlePath))->build($this->createContainerBuilder());
    }

    // @deprecated tag:v6.8.0 - remove together with the XML service definition deprecation
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
        $loader = $this->createMock(PhpFileLoader::class);
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
}
