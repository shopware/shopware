<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedLanguageRouteInvalidator implements EventSubscriberInterface
{
    private Connection $connection;

    private CacheInvalidationLogger $logger;

    public function __construct(
        CacheInvalidationLogger $logger,
        Connection $connection
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
    }

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
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $this->logger->log(array_merge(
            $this->getChangedAssignments($event),
            $this->getChangedLanguages($event)
        ));
    }

    private function getChangedLanguages(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(LanguageDefinition::ENTITY_NAME);
        if (empty($ids)) {
            return [];
        }

        //Used to detect changes to the language itself and invalidate the route for all sales channels in which the language is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(sales_channel_id)) as id FROM sales_channel_language WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_map([CachedLanguageRoute::class, 'buildName'], $ids);
    }

    private function getChangedAssignments(EntityWrittenContainerEvent $event): array
    {
        //Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(SalesChannelLanguageDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'salesChannelId');

        return array_map([CachedLanguageRoute::class, 'buildName'], $ids);
    }
}
