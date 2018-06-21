<?php

namespace Shopware\Core\Framework\Translation;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Core\System\Snippet\SnippetDefinition;
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
            GenericWrittenEvent::NAME => 'invalidate'
        ];
    }

    public function invalidate(GenericWrittenEvent $event): void
    {
        $snippetEvent = $event->getEventByDefinition(SnippetDefinition::class);
        if (!$snippetEvent) {
            return;
        }

        $contextHash = md5(
            $snippetEvent->getContext()->getTenantId()
            . $snippetEvent->getContext()->getLanguageId()
            . $snippetEvent->getContext()->getFallbackLanguageId()
        );

        $cacheItem = $this->cache->getItem('translation.catalog.' . $contextHash);

        if (!$cacheItem->isHit()) {
            return;
        }

        $this->cache->deleteItem($cacheItem->getKey());
    }
}