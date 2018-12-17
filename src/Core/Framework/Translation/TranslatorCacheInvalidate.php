<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
use Shopware\Core\Framework\Snippet\SnippetEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TranslatorCacheInvalidate implements EventSubscriberInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public static function getSubscribedEvents()
    {
        return [
            SnippetEvents::SNIPPET_WRITTEN_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_DELETED_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_SET_DELETED_EVENT => 'invalidate',
        ];
    }

    public function invalidate(EntityWrittenEvent $event): void
    {
        $snippetSetIds = [];
        if ($event->getDefinition() === SnippetDefinition::class) {
            foreach ($event->getPayload() as $snippet) {
                $snippetSetIds[] = $snippet['setId'];
            }
        } elseif ($event->getDefinition() === SnippetSetDefinition::class) {
            $snippetSetIds = $event->getIds();
        }
        $snippetSetIds = array_unique($snippetSetIds);

        foreach ($snippetSetIds as $id) {
            $cacheItem = $this->cache->getItem('translation.catalog.' . $id);
            if (!$cacheItem->isHit()) {
                continue;
            }
            $this->cache->deleteItem($cacheItem->getKey());
        }
    }
}
