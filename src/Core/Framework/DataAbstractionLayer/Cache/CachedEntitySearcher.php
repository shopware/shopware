<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\Framework\MessageQueue\MessageQueueStatsDefinition;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

class CachedEntitySearcher implements EntitySearcherInterface
{
    public const BLACKLIST = [
        VersionDefinition::class,
        VersionCommitDefinition::class,
        VersionCommitDataDefinition::class,
        NumberRangeStateDefinition::class,
        PluginDefinition::class,
        LogEntryDefinition::class,
        MessageQueueStatsDefinition::class,
        AppDefinition::class,
    ];

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntitySearcherInterface
     */
    private $decorated;

    public function __construct(
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapterInterface $cache,
        EntitySearcherInterface $decorated
    ) {
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->decorated = $decorated;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$context->getUseCache()) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        if (\in_array($definition->getClass(), self::BLACKLIST, true)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $key = $this->cacheKeyGenerator->getSearchCacheKey($definition, $criteria, $context);

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        $result = $this->decorated->search($definition, $criteria, $context);

        $item->set($result);

        $tags = $this->cacheKeyGenerator->getSearchTags($definition, $criteria);
        if (!$item instanceof CacheItem) {
            throw new \RuntimeException(sprintf('Cache adapter has to return instance of %s', CacheItem::class));
        }

        /* @var CacheItem $item */
        $item->tag($tags);

        $this->cache->save($item);

        return $result;
    }
}
