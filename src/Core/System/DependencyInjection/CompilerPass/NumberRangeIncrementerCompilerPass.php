<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class NumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopware.number_range.config.connection') !== null) {
            return;
        }

        // we remove service from container when required configurations are missing
        // we always keep mysql storage so MigrateIncrementStorageCommand works
        $container->removeDefinition('shopware.number_range.redis');
        $container->removeDefinition(IncrementRedisStorage::class);
    }
}
