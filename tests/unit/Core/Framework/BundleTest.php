<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Kernel;
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

    public function testGetTwigComponentNamespace(): void
    {
        $bundleClass = new class extends Bundle {};

        static::assertSame(
            $bundleClass::getTwigComponentNamespace(),
            $bundleClass->getNamespace() . '\\Resources\\views\\components\\'
        );
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
