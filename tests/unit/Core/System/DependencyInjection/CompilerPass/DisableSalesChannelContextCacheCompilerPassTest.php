<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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
    public function testRemovesSalesChannelContextCacheDecoratorsWhenAtsIsRunning(): void
    {
        $container = $this->createContainer(atsRunning: true);

        (new DisableSalesChannelContextCacheCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(CachedBaseSalesChannelContextFactory::class));
        static::assertFalse($container->hasDefinition(CachedSalesChannelContextFactory::class));
    }

    public function testKeepsSalesChannelContextCacheDecoratorsOutsideAts(): void
    {
        $container = $this->createContainer(atsRunning: false);

        (new DisableSalesChannelContextCacheCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(CachedBaseSalesChannelContextFactory::class));
        static::assertTrue($container->hasDefinition(CachedSalesChannelContextFactory::class));
    }

    private function createContainer(bool $atsRunning): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.ats_running', $atsRunning);
        $container->setDefinition(CachedBaseSalesChannelContextFactory::class, new Definition());
        $container->setDefinition(CachedSalesChannelContextFactory::class, new Definition());

        return $container;
    }
}
