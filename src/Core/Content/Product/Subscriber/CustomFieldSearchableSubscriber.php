<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
class CustomFieldSearchableSubscriber implements EventSubscriberInterface
{
    private const BATCH_SIZE = 50;

    /**
     * All ProductIndexer updaters except SEARCH_KEYWORD_UPDATER
     *
     * @var list<string>
     */
    private static array $skipUpdaters = [
        ProductIndexer::INHERITANCE_UPDATER,
        ProductIndexer::STOCK_UPDATER,
        ProductIndexer::VARIANT_LISTING_UPDATER,
        ProductIndexer::CHILD_COUNT_UPDATER,
        ProductIndexer::CATEGORY_DENORMALIZER_UPDATER,
        ProductIndexer::CHEAPEST_PRICE_UPDATER,
        ProductIndexer::RATING_AVERAGE_UPDATER,
        ProductIndexer::STATES_UPDATER,
        ProductIndexer::STREAM_UPDATER,
        ProductIndexer::MANY_TO_MANY_ID_FIELD_UPDATER,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $messageBus,
        private readonly SearchKeywordUpdater $searchKeywordUpdater
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onCustomFieldWritten',
        ];
    }

    public function onCustomFieldWritten(EntityWrittenContainerEvent $containerEvent): void
    {
        $customFieldWrittenEvent = $containerEvent->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);

        if ($customFieldWrittenEvent === null) {
            return;
        }

        $customFieldIds = [];
        foreach ($customFieldWrittenEvent->getWriteResults() as $writeResult) {
            if (\array_key_exists('searchable', $writeResult->getPayload())) {
                $customFieldIds[] = $writeResult->getPrimaryKey();
            }
        }
        if (empty($customFieldIds)) {
            return;
        }

        if (!$this->hasSearchConfig($customFieldIds)) {
            return;
        }

        $customFieldNames = $this->getCustomFieldNames($customFieldIds);
        if (empty($customFieldNames)) {
            return;
        }

        $this->searchKeywordUpdater->reset();

        $productIds = $this->findProductsWithCustomFields($customFieldNames);
        if (empty($productIds)) {
            return;
        }

        $this->dispatchIndexingMessages($productIds, $containerEvent->getContext());
    }

    /**
     * @param array<string> $customFieldIds
     */
    private function hasSearchConfig(array $customFieldIds): bool
    {
        $customFieldIdsBytes = Uuid::fromHexToBytesList($customFieldIds);

        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM product_search_config_field WHERE custom_field_id IN (:customFieldIds) LIMIT 1',
            ['customFieldIds' => $customFieldIdsBytes],
            ['customFieldIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $customFieldIds
     *
     * @return array<string>
     */
    private function getCustomFieldNames(array $customFieldIds): array
    {
        $customFieldIdsBytes = Uuid::fromHexToBytesList($customFieldIds);

        return $this->connection->fetchFirstColumn(
            'SELECT name FROM custom_field WHERE id IN (:customFieldIds)',
            ['customFieldIds' => $customFieldIdsBytes],
            ['customFieldIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $customFieldNames
     *
     * @return array<string>
     */
    private function findProductsWithCustomFields(array $customFieldNames): array
    {
        if (empty($customFieldNames)) {
            return [];
        }

        [$whereClause, $queryParams] = $this->buildCustomFieldWhereClause($customFieldNames);

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_translation.product_id)) as id
            FROM product_translation
            WHERE ' . $whereClause,
            $queryParams
        );
    }

    /**
     * @param array<string> $customFieldNames
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildCustomFieldWhereClause(array $customFieldNames): array
    {
        $whereParts = [];
        $queryParams = [];

        foreach ($customFieldNames as $index => $fieldName) {
            $jsonPath = '$.' . $fieldName;
            $paramName = 'path' . $index;

            $whereParts[] = \sprintf(
                'JSON_CONTAINS_PATH(product_translation.custom_fields, \'one\', :%1$s)
                AND JSON_EXTRACT(product_translation.custom_fields, :%1$s) IS NOT NULL
                AND JSON_EXTRACT(product_translation.custom_fields, :%1$s) != \'null\'',
                $paramName
            );
            $queryParams[$paramName] = $jsonPath;
        }

        return ['(' . implode(') OR (', $whereParts) . ')', $queryParams];
    }

    /**
     * @param array<string> $productIds
     */
    private function dispatchIndexingMessages(array $productIds, Context $context): void
    {
        $chunks = \array_chunk($productIds, self::BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $message = new ProductIndexingMessage($chunk, null, $context, true);
            $message->setIndexer('product.indexer');
            $message->setSkip(self::$skipUpdaters);

            $this->messageBus->dispatch($message);
        }
    }
}
