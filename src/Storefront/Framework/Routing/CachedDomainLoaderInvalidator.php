<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedDomainLoaderInvalidator implements EventSubscriberInterface
{
    private CacheInvalidator $logger;

    /**
     * @internal
     */
    public function __construct(CacheInvalidator $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        if ($event->getEventByEntityName(SalesChannelDefinition::ENTITY_NAME)) {
            $this->logger->invalidate([CachedDomainLoader::CACHE_KEY]);
        }
    }
}
