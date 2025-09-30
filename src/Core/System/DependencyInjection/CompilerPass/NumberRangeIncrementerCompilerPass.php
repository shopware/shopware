<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class NumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopware.number_range.increment_storage');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopware.number_range.redis');
                $container->removeDefinition(IncrementRedisStorage::class);
                break;
            case 'redis':
                if ($container->getParameter('shopware.number_range.config.connection') === null) {
                    throw DependencyInjectionException::redisNotConfiguredForNumberRangeIncrementer();
                }

                $container->removeDefinition(IncrementSqlStorage::class);
                break;
        }
    }
}
