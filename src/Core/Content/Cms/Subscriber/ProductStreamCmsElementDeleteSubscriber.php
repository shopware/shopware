<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore This would be useless as a unit test. It is integration tested here: \Shopware\Tests\Integration\Core\Content\Cms\Subscriber\ProductStreamCmsElementDeleteSubscriberTest
 */
#[Package('discovery')]
class ProductStreamCmsElementDeleteSubscriber implements EventSubscriberInterface
{
    private const PRODUCT_SLIDER_TYPE = 'product-slider';

    /**
     * @var array<string, array{table: string, idColumn: string, configColumn: string}>
     */
    private const SLOT_CONFIG_REFERENCES = [
        CategoryDefinition::ENTITY_NAME => [
            'table' => 'category_translation',
            'idColumn' => 'category_id',
            'configColumn' => 'slot_config',
        ],
        ProductDefinition::ENTITY_NAME => [
            'table' => 'product_translation',
            'idColumn' => 'product_id',
            'configColumn' => 'slot_config',
        ],
        LandingPageDefinition::ENTITY_NAME => [
            'table' => 'landing_page_translation',
            'idColumn' => 'landing_page_id',
            'configColumn' => 'slot_config',
        ],
        SalesChannelDefinition::ENTITY_NAME => [
            'table' => 'sales_channel_translation',
            'idColumn' => 'sales_channel_id',
            'configColumn' => 'home_slot_config',
        ],
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductStreamDefinition $productStreamDefinition
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $productStreamIds = array_values($event->getIds(ProductStreamDefinition::ENTITY_NAME));

        if ($productStreamIds === []) {
            return;
        }

        $restrictions = [];
        $this->addUsages(
            $restrictions,
            CmsSlotDefinition::ENTITY_NAME,
            $this->fetchCmsSlotUsages($productStreamIds)
        );

        foreach (self::SLOT_CONFIG_REFERENCES as $entityName => $reference) {
            $this->addUsages(
                $restrictions,
                $entityName,
                $this->fetchSlotConfigUsages(
                    $reference['table'],
                    $reference['idColumn'],
                    $reference['configColumn'],
                    $productStreamIds
                )
            );
        }

        if ($restrictions === []) {
            return;
        }

        throw DataAbstractionLayerException::restrictDeleteViolations($this->productStreamDefinition, $restrictions);
    }

    /**
     * @param list<string> $productStreamIds
     *
     * @return list<string>
     */
    private function fetchCmsSlotUsages(array $productStreamIds): array
    {
        [$streamReferenceCondition, $parameters] = $this->buildProductStreamReferenceCondition(
            '`cms_slot_translation`.`config`',
            '$.products',
            $productStreamIds
        );

        $parameters['productSliderType'] = self::PRODUCT_SLIDER_TYPE;

        return $this->normalizeIds($this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`cms_slot_translation`.`cms_slot_id`))
             FROM `cms_slot_translation`
             INNER JOIN `cms_slot`
                ON `cms_slot`.`id` = `cms_slot_translation`.`cms_slot_id`
                AND `cms_slot`.`version_id` = `cms_slot_translation`.`cms_slot_version_id`
             WHERE `cms_slot`.`type` = :productSliderType
                AND ' . $streamReferenceCondition,
            $parameters
        ));
    }

    /**
     * @param list<string> $productStreamIds
     *
     * @return list<string>
     */
    private function fetchSlotConfigUsages(
        string $table,
        string $idColumn,
        string $configColumn,
        array $productStreamIds
    ): array {
        $escapedConfigColumn = \sprintf('`%s`.`%s`', $table, $configColumn);
        [$streamReferenceCondition, $parameters] = $this->buildProductStreamReferenceCondition(
            $escapedConfigColumn,
            '$.*.products',
            $productStreamIds
        );

        return $this->normalizeIds($this->connection->fetchFirstColumn(
            \sprintf(
                'SELECT DISTINCT LOWER(HEX(`%s`.`%s`))
                 FROM `%s`
                 WHERE `%s`.`%s` IS NOT NULL
                    AND %s',
                $table,
                $idColumn,
                $table,
                $table,
                $configColumn,
                $streamReferenceCondition
            ),
            $parameters
        ));
    }

    /**
     * @param list<string> $productStreamIds
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildProductStreamReferenceCondition(string $configColumn, string $jsonPath, array $productStreamIds): array
    {
        $parameters = [
            'productStreamSource' => 'product_stream',
        ];
        $valueConditions = [];

        foreach ($productStreamIds as $index => $productStreamId) {
            $parameter = 'productStreamId' . $index;
            $parameters[$parameter] = $productStreamId;
            $valueConditions[] = \sprintf(
                'JSON_CONTAINS(JSON_EXTRACT(%s, \'%s\'), JSON_OBJECT(\'source\', :productStreamSource, \'value\', :%s)) = 1',
                $configColumn,
                $jsonPath,
                $parameter
            );
        }

        return [
            '(' . implode(' OR ', $valueConditions) . ')',
            $parameters,
        ];
    }

    /**
     * @param array<string, list<string>> $restrictions
     * @param list<string> $ids
     */
    private function addUsages(array &$restrictions, string $entityName, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $restrictions[$entityName] = array_values(array_unique([
            ...($restrictions[$entityName] ?? []),
            ...$ids,
        ]));
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        $ids = array_filter($ids, static fn (mixed $id): bool => \is_string($id) && $id !== '');

        return array_values(array_unique($ids));
    }
}
