<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
final class DisableSalesChannelContextCacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopware.ats_running') !== true) {
            return;
        }

        $container->removeDefinition(CachedBaseSalesChannelContextFactory::class);
        $container->removeDefinition(CachedSalesChannelContextFactory::class);
    }
}
