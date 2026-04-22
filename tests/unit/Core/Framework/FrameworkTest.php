<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(Framework::class)]
class FrameworkTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $framework = new Framework();

        static::assertEquals(-1, $framework->getTemplatePriority());
    }

    public function testFeatureFlagRegisteredOnBoot(): void
    {
        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects($this->once())->method('register');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(DefinitionInstanceRegistry::class, $this->createMock(DefinitionInstanceRegistry::class));
        $container->set(SalesChannelDefinitionInstanceRegistry::class, $this->createMock(SalesChannelDefinitionInstanceRegistry::class));
        $container->set(ExtensionRegistry::class, $this->createMock(ExtensionRegistry::class));
        $container->setParameter('kernel.cache_dir', '/tmp');
        $container->setParameter('shopware.cache.cache_compression', true);
        $container->setParameter('shopware.cache.cache_compression_method', 'gzip');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $framework = new Framework();
        $framework->setContainer($container);

        $framework->boot();
    }

    public function testConfigureRoutesInTestEnvironment(): void
    {
        $confDir = (new Framework())->getPath() . '/Resources/config';
        $expected = [
            $confDir . '/{routes}' . Kernel::CONFIG_EXTS,
            $confDir . '/{routes}_test' . Kernel::CONFIG_EXTS,
            $confDir . '/{routes}/test/*' . Kernel::CONFIG_EXTS,
            $confDir . '/{routes}/test/**/*' . Kernel::CONFIG_EXTS,
        ];

        (new Framework())->configureRoutes(
            $this->buildRoutingConfigurator($expected),
            'test',
        );

        static::assertSame([], $expected, 'configureRoutes did not emit all expected glob imports');
    }

    public function testConfigureRoutesInProdEnvironmentSkipsTestGlob(): void
    {
        $confDir = (new Framework())->getPath() . '/Resources/config';
        $expected = [
            $confDir . '/{routes}' . Kernel::CONFIG_EXTS,
            $confDir . '/{routes}_prod' . Kernel::CONFIG_EXTS,
            $confDir . '/{routes}/prod/**/*' . Kernel::CONFIG_EXTS,
        ];

        (new Framework())->configureRoutes(
            $this->buildRoutingConfigurator($expected),
            'prod',
        );

        static::assertSame([], $expected, 'configureRoutes did not emit all expected glob imports');
    }

    public function testConfigureRoutesReturnsEarlyWhenConfigDirectoryMissing(): void
    {
        $framework = new class extends Framework {
            public function getPath(): string
            {
                return '/does/not/exist/' . uniqid('shopware-test-', true);
            }
        };

        $loader = $this->createMock(PhpFileLoader::class);
        $loader->expects($this->never())->method('import');

        $framework->configureRoutes(
            new RoutingConfigurator(new RouteCollection(), $loader, '/tmp', '/tmp'),
            'prod',
        );
    }

    /**
     * @param list<string> $expectedPatterns by-reference; each observed call shifts one pattern off the front.
     */
    private function buildRoutingConfigurator(array &$expectedPatterns): RoutingConfigurator
    {
        $loader = $this->createMock(PhpFileLoader::class);
        $loader->expects($this->exactly(\count($expectedPatterns)))
            ->method('import')
            ->willReturnCallback(function (mixed $resource, ?string $type) use (&$expectedPatterns): array {
                static::assertSame(array_shift($expectedPatterns), $resource);
                static::assertSame('glob', $type);

                return [];
            });

        return new RoutingConfigurator(new RouteCollection(), $loader, '/tmp', '/tmp');
    }
}
