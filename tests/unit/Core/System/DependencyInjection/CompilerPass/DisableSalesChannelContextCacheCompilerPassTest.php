<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\DependencyInjection\CompilerPass\DisableSalesChannelContextCacheCompilerPass;
use Shopware\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DisableSalesChannelContextCacheCompilerPass::class)]
class DisableSalesChannelContextCacheCompilerPassTest extends TestCase
{
    use EnvTestBehaviour;

    public function testRemovesSalesChannelContextCacheDecoratorsWhenAtsIsRunning(): void
    {
        $this->setEnvVars(['ATS_RUNNING' => '1']);
        $container = $this->createContainer();

        (new DisableSalesChannelContextCacheCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(CachedBaseSalesChannelContextFactory::class));
        static::assertFalse($container->hasDefinition(CachedSalesChannelContextFactory::class));
    }

    public function testKeepsSalesChannelContextCacheDecoratorsOutsideAts(): void
    {
        $this->setEnvVars(['ATS_RUNNING' => null]);
        $container = $this->createContainer();

        (new DisableSalesChannelContextCacheCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(CachedBaseSalesChannelContextFactory::class));
        static::assertTrue($container->hasDefinition(CachedSalesChannelContextFactory::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CachedBaseSalesChannelContextFactory::class, new Definition());
        $container->setDefinition(CachedSalesChannelContextFactory::class, new Definition());

        return $container;
    }
}
