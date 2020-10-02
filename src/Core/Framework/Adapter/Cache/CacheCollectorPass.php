<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @see https://github.com/symfony/symfony/pull/38059
 */
class CacheCollectorPass implements CompilerPassInterface
{
    private $dataCollectorCacheId;

    private $cachePoolTag;

    private $cachePoolRecorderInnerSuffix;

    public function __construct(string $dataCollectorCacheId = 'data_collector.cache', string $cachePoolTag = 'cache.pool', string $cachePoolRecorderInnerSuffix = '.recorder_inner')
    {
        $this->dataCollectorCacheId = $dataCollectorCacheId;
        $this->cachePoolTag = $cachePoolTag;
        $this->cachePoolRecorderInnerSuffix = $cachePoolRecorderInnerSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->dataCollectorCacheId)) {
            return;
        }

        foreach ($container->findTaggedServiceIds($this->cachePoolTag) as $id => $attributes) {
            $poolName = $attributes[0]['name'] ?? $id;

            $this->addToCollector($id, $poolName, $container);
        }
    }

    private function addToCollector(string $id, string $name, ContainerBuilder $container): void
    {
        $definition = $container->getDefinition($id);
        if ($definition->isAbstract()) {
            return;
        }

        $collectorDefinition = $container->getDefinition($this->dataCollectorCacheId);
        $recorder = new Definition(is_subclass_of($definition->getClass(), TagAwareAdapterInterface::class) ? TraceableTagAwareAdapter::class : TraceableAdapter::class);
        $recorder->setTags($definition->getTags());
        if (!$definition->isPublic() || !$definition->isPrivate()) {
            $recorder->setPublic($definition->isPublic());
        }
        $recorder->setArguments([new Reference($innerId = $id . $this->cachePoolRecorderInnerSuffix)]);

        $definition->setTags([]);
        $definition->setPublic(false);

        $container->setDefinition($innerId, $definition);
        $container->setDefinition($id, $recorder);

        // Tell the collector to add the new instance
        $collectorDefinition->addMethodCall('addInstance', [$name, new Reference($id)]);
        $collectorDefinition->setPublic(false);
    }
}
