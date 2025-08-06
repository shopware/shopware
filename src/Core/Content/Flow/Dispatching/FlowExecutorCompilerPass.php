<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('after-sales')]
class FlowExecutorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('shopware.flow.async')) {
            $container->setParameter('shopware.flow.async', false);
        }

        $shouldRunAsync = $container->getParameter('shopware.flow.async') ?? false;

        if (!$shouldRunAsync) {
            $flowExecutor = $container->getDefinition('Shopware\Core\Content\Flow\Dispatching\FlowExecutor');

            $flowExecutor->replaceArgument(8, null);
        }
    }
}
