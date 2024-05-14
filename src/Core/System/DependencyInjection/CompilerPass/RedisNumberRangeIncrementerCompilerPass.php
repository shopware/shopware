<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - can be renamed to NumberRangeIncrementStorageCompilerPass
 */
#[Package('core')]
class RedisNumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    private const DEPRECATED_MAPPING = [
        'SQL' => 'mysql',
        'Redis' => 'redis',
    ];

    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopware.number_range.increment_storage');

        // @deprecated tag:v6.7.0 - remove this if block
        if (\in_array($storage, array_keys(self::DEPRECATED_MAPPING), true)) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', sprintf(
                'Parameter value "%s" will not be supported. Please use one of the following values: %s',
                $storage,
                implode(', ', self::DEPRECATED_MAPPING)
            ));

            $container->setParameter('shopware.number_range.increment_storage', self::DEPRECATED_MAPPING[$storage]);
        }

        // @deprecated tag:v6.7.0 - remove this if block
        if ($container->hasParameter('shopware.number_range.redis_url') && $container->getParameter('shopware.number_range.redis_url') !== false) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'Parameter "shopware.number_range.redis_url" is deprecated and will be removed. Please use "shopware.number_range.config.dsn" instead.'
            );

            $container->setParameter('shopware.number_range.config.dsn', $container->getParameter('shopware.number_range.redis_url'));
        }

        switch ($storage) {
            case 'SQL': // @deprecated tag:v6.7.0 - remove this case
                $container->removeDefinition('shopware.number_range.redis');
                $container->removeDefinition(IncrementRedisStorage::class);
                break;
            case 'Redis': // @deprecated tag:v6.7.0 - remove this case
                if (!$container->hasParameter('shopware.number_range.config.dsn')) {
                    throw DependencyInjectionException::redisNotConfiguredForNumberRangeIncrementer();
                }

                $container->removeDefinition(IncrementSqlStorage::class);
                break;
            case 'mysql':
                $container->removeDefinition('shopware.number_range.redis');
                $container->removeDefinition(IncrementRedisStorage::class);
                break;
            case 'redis':
                if (!$container->hasParameter('shopware.number_range.config.dsn')) {
                    throw DependencyInjectionException::redisNotConfiguredForNumberRangeIncrementer();
                }

                $container->removeDefinition(IncrementSqlStorage::class);
                break;
        }
    }
}
