<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - will be considered internal from 6.8.0.0 onwards
 */
#[Package('framework')]
class ElasticsearchProfileCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $isDebugEnabled = $container->getParameterBag()->resolveValue($container->getParameter('kernel.debug'));

        if (!$isDebugEnabled) {
            $container->removeDefinition(DataCollector::class);

            return;
        }

        // we need direct access to the ClientProfiler, so it cannot be wrapped in a lazy proxy
        $container->getDefinition(Client::class)->setLazy(false);
        $container->getDefinition('admin.openSearch.client')->setLazy(false);
    }
}
