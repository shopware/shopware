<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class RedisNumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('shopware.number_range.redis_url')) {
            $container->removeDefinition('shopware.number_range.redis');
            $container->removeDefinition(IncrementRedisStorage::class);
        }
    }
}
