<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

#[Package('core')]
class RateLimiterCompilerPass implements CompilerPassInterface
{
    private const DEFAULT_ENABLED_STATE = true;

    private const DEFAULT_CACHE_POOL = 'cache.rate_limiter';

    private const DEFAULT_LOCK_FACTORY = 'lock.factory';

    public function process(ContainerBuilder $container): void
    {
        $rateLimiter = $container->getDefinition(RateLimiter::class);

        /** @var array<string, array<string, string>> $rateLimiterConfig */
        $rateLimiterConfig = $container->getParameter('shopware.api.rate_limiter');

        foreach ($rateLimiterConfig as $name => $config) {
            $this->setConfigDefaults($config);

            $def = new Definition(RateLimiterFactory::class);
            $def->addArgument($config + ['id' => $name]); // config

            $cacheDef = new Definition(CacheStorage::class);
            $cacheDef->addArgument(new Reference($config['cache_pool']));

            $def->addArgument($cacheDef);
            $def->addArgument(new Reference(SystemConfigService::class));
            $def->addArgument(new Reference($config['lock_factory']));

            $rateLimiter->addMethodCall('registerLimiterFactory', [$name, $def]);
        }

        $container->setDefinition('shopware.rate_limiter', $rateLimiter);
    }

    /**
     * @param array<string, array<string, int|string>|bool|string|int> $config
     */
    private function setConfigDefaults(array &$config): void
    {
        if (!\array_key_exists('enabled', $config)) {
            $config['enabled'] = self::DEFAULT_ENABLED_STATE;
        }

        if (!\array_key_exists('cache_pool', $config)) {
            $config['cache_pool'] = self::DEFAULT_CACHE_POOL;
        }

        if (!\array_key_exists('lock_factory', $config)) {
            $config['lock_factory'] = self::DEFAULT_LOCK_FACTORY;
        }
    }
}
