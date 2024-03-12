<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Media\Infrastructure\Path\FastlyMediaReverseProxy;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('storefront')]
class MediaReverserProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('shopware.cdn.fastly.enabled')) {
            return;
        }

        $container->setAlias('shopware.media.reverse_proxy', FastlyMediaReverseProxy::class);
    }
}
