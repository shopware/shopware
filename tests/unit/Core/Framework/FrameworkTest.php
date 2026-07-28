<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
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

    // building the full bundle container is legitimately slower under coverage instrumentation,
    // and whichever build test runs first also pays the one-time symfony-dependency-injection load
    #[MaximumDuration(2000)]
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

    #[MaximumDuration(2000)]
    public function testBuildRegistersTestServicesInTestEnvironment(): void
    {
        $container = $this->buildContainer('test');

        static::assertTrue($container->hasDefinition(AclTestController::class));

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof DisableRateLimiterCompilerPass));
        static::assertNotEmpty(array_filter($passes, static fn ($pass) => $pass instanceof ContainerVisibilityCompilerPass));
    }

    public function testFeatureFlagRegisteredOnBoot(): void
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
        $container->setParameter('shopware.cache.compress', true);
        $container->setParameter('shopware.cache.compression_method', 'gzip');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->compile();

        $framework = new Framework();
        $framework->setContainer($container);

        $framework->boot();
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
