<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Snippet\SnippetEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class TranslatorCacheInvalidate implements EventSubscriberInterface
{
    private Connection $connection;

    private CacheInvalidator $cacheInvalidator;

    /**
     * @internal
     */
    public function __construct(CacheInvalidator $cacheInvalidator, Connection $connection)
    {
        $this->connection = $connection;
        $this->cacheInvalidator = $cacheInvalidator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SnippetEvents::SNIPPET_WRITTEN_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_DELETED_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_SET_DELETED_EVENT => 'invalidate',
        ];
    }

    public function invalidate(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() === SnippetSetDefinition::ENTITY_NAME) {
            $this->clearCache($event->getIds());

            return;
        }

        if ($event->getEntityName() === SnippetDefinition::ENTITY_NAME) {
            $snippetIds = $event->getIds();

            $setIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(snippet_set_id)) FROM snippet WHERE HEX(id) IN (:ids)',
                ['ids' => $snippetIds],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->clearCache($setIds);
        }
    }

    /**
     * @param array<string> $snippetSetIds
     */
    private function clearCache(array $snippetSetIds): void
    {
        $snippetSetIds = array_unique($snippetSetIds);

        $snippetSetCacheKeys = array_map(function (string $setId) {
            return 'translation.catalog.' . $setId;
        }, $snippetSetIds);

        $this->cacheInvalidator->invalidate($snippetSetCacheKeys, true);
    }
}
