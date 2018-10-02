<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Doctrine;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class BridgeDatabaseCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('doctrine.dbal.connection_factory')
                  ->addArgument(new Reference('kernel'));
    }
}
