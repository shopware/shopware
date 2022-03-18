<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RedisNumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('shopware.number_range.redis_url')) {
            $container->removeDefinition('shopware.number_range.redis');
            $container->removeDefinition(IncrementRedisStorage::class);

            return;
        }

        $container->removeDefinition(IncrementSqlStorage::class);
        $container->setAlias(IncrementSqlStorage::class, IncrementRedisStorage::class);
    }
}
