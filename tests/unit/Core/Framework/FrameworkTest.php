<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Shopware\Core\Framework\Test\DependencyInjection\CompilerPass\ContainerVisibilityCompilerPass;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Framework::class)]
class FrameworkTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $framework = new Framework();

        static::assertSame(-1, $framework->getTemplatePriority());
    }

    public function testBuild(): void
    {
        $container = $this->buildContainer('prod');

        static::assertSame('en-GB', $container->getParameter('locale'));
        static::assertTrue($container->hasDefinition(HtmlSanitizer::class));
        static::assertTrue($container->hasDefinition(CacheClearer::class));

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof FeatureFlagCompilerPass));
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof EntityCompilerPass));
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof McpToolDiscoveryCompilerPass));

        static::assertFalse($container->hasDefinition(AclTestController::class));
        static::assertEmpty(array_filter($passes, static fn ($pass) => $pass instanceof DisableRateLimiterCompilerPass));
    }

    public function testBuildRegistersTestServicesInTestEnvironment(): void
    {
        $container = $this->buildContainer('test');

        static::assertTrue($container->hasDefinition(AclTestController::class));

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof DisableRateLimiterCompilerPass));
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof ContainerVisibilityCompilerPass));
    }

    #[TestDox('boot registers the feature flags and applies the runtime configuration')]
    #[DataProvider('bootConfigurationProvider')]
    public function testBootAppliesRuntimeConfiguration(bool $compress, string $compressMethod, bool $debug): void
    {
        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects($this->once())->method('register');

        $stampedeProtectionConfigurator = $this->createMock(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->expects($this->once())->method('apply');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(StampedeProtectionConfigurator::class, $stampedeProtectionConfigurator);
        $container->set(DefinitionInstanceRegistry::class, static::createStub(DefinitionInstanceRegistry::class));
        $container->set(SalesChannelDefinitionInstanceRegistry::class, static::createStub(SalesChannelDefinitionInstanceRegistry::class));
        $container->setParameter('kernel.cache_dir', '/tmp');
        $container->setParameter('shopware.cache.compress', $compress);
        $container->setParameter('shopware.cache.compression_method', $compressMethod);
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.environment', 'test');
        $container->compile();

        $framework = new Framework();
        $framework->setContainer($container);

        // boot() assigns process-global statics: restore them so no other test inherits this run
        $before = [CacheValueCompressor::$compress, CacheValueCompressor::$compressMethod, Feature::$emitDeprecations];

        try {
            $framework->boot();

            static::assertSame($compress, CacheValueCompressor::$compress);
            static::assertSame($compressMethod, CacheValueCompressor::$compressMethod);
            static::assertSame($debug, Feature::$emitDeprecations);
        } finally {
            [CacheValueCompressor::$compress, CacheValueCompressor::$compressMethod, Feature::$emitDeprecations] = $before;
        }
    }

    public static function bootConfigurationProvider(): \Generator
    {
        yield 'debug with gzip compression' => [true, 'gzip', true];
        yield 'no debug, no compression, zstd configured' => [false, 'zstd', false];
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        // buildDefaultConfig() loads Resources/config/packages/*, whose root
        // keys require the extensions of these bundles to be registered
        foreach ([new FrameworkBundle(), new TwigBundle(), new MonologBundle()] as $bundle) {
            $extension = $bundle->getContainerExtension();
            static::assertNotNull($extension);
            $container->registerExtension($extension);
        }

        $framework = new Framework();
        $container->registerExtension($framework->getContainerExtension());
        $framework->build($container);

        return $container;
    }
}
