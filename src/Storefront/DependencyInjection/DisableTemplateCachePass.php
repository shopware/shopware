<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('storefront')]
class DisableTemplateCachePass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.cache_warmer') || !$container->hasDefinition('twig.template_cache_warmer')) {
            return;
        }
        // disable cache warm-up as it breaks the inheritance
        $container->getDefinition('twig.cache_warmer')->clearTag('kernel.cache_warmer');
        $container->getDefinition('twig.template_cache_warmer')->clearTag('kernel.cache_warmer');
    }
}
