<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
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
            EntityWrittenContainerEvent::NAME => 'invalidate',
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $snippetEvent = $event->getEventByDefinition(SnippetDefinition::class);
        if (!$snippetEvent) {
            return;
        }

        $contextHash = md5(
            $snippetEvent->getContext()->getLanguageId()
            . $snippetEvent->getContext()->getFallbackLanguageId()
        );

        $cacheItem = $this->cache->getItem('translation.catalog.' . $contextHash);

        if (!$cacheItem->isHit()) {
            return;
        }

        $this->cache->deleteItem($cacheItem->getKey());
    }
}
