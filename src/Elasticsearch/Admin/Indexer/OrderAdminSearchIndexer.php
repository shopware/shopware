<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

#[Package('inventory')]
final class OrderAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'order-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize, Defaults::LIVE_VERSION);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $orderIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'orderNumber',
            'amountTotal',
            'orderDateTime',
            'stateId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(OrderAddressDefinition::ENTITY_NAME, [
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
            'orderId',
        ]);

        $orderDocuments = $event->getPrimaryKeysWithPropertyChange(DocumentDefinition::ENTITY_NAME, [
            'config',
            'orderId',
        ]);

        if (!empty($addresses) || !empty($orderDocuments)) {
            $orderIds = array_merge($orderIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $multiplePrimaryKeyWrittenEvent = $event;
        $tags = $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(OrderTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['orderId'])) {
                $orderIds[] = $pks['orderId'];
            }
        }

        return array_values(array_unique(array_filter($orderIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'orderNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'amountTotal' => AbstractElasticsearchDefinition::FLOAT_FIELD,
            'orderDateTime' => ElasticsearchFieldBuilder::datetime(),
            'stateId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{id:string, text:string}>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(`order`.id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   GROUP_CONCAT(DISTINCT country_translation.name SEPARATOR " ") as country,
                   GROUP_CONCAT(DISTINCT order_address.city SEPARATOR " ") as city,
                   GROUP_CONCAT(DISTINCT order_address.street SEPARATOR " ") as street,
                   GROUP_CONCAT(DISTINCT order_address.zipcode SEPARATOR " ") as zipcode,
                   GROUP_CONCAT(DISTINCT order_address.phone_number SEPARATOR " ") as phone_number,
                   GROUP_CONCAT(DISTINCT order_address.additional_address_line1 SEPARATOR " ") as additional_address_line1,
                   GROUP_CONCAT(DISTINCT order_address.additional_address_line2 SEPARATOR " ") as additional_address_line2,
                   GROUP_CONCAT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(document.config, "$.documentNumber")) SEPARATOR " ") as documentNumber,
                   order_customer.first_name,
                   order_customer.last_name,
                   order_customer.email,
                   order_customer.company,
                   order_customer.customer_number,
                   `order`.order_number,
                   `order`.amount_total,
                   `order`.order_date_time,
                   LOWER(HEX(`order`.state_id)) AS stateId,
                   LOWER(HEX(`order`.sales_channel_id)) AS salesChannelId,
                   `order`.affiliate_code AS affiliateCode,
                   `order`.campaign_code AS campaignCode,
                   `order`.created_at as createdAt,
                   order_delivery.tracking_codes
            FROM `order`
                LEFT JOIN order_customer
                    ON `order`.id = order_customer.order_id AND `order`.version_id = order_customer.order_version_id
                LEFT JOIN order_address
                    ON `order`.id = order_address.order_id AND `order`.version_id = order_address.order_version_id
                LEFT JOIN country
                    ON order_address.country_id = country.id
                LEFT JOIN country_translation
                    ON country.id = country_translation.country_id
                LEFT JOIN order_tag
                    ON `order`.id = order_tag.order_id AND `order`.version_id = order_tag.order_version_id
                LEFT JOIN tag
                    ON order_tag.tag_id = tag.id
                LEFT JOIN order_delivery
                    ON `order`.id = order_delivery.order_id AND `order`.version_id = order_delivery.order_version_id
                LEFT JOIN document
                    ON `order`.id = document.order_id AND document.order_version_id = `order`.version_id
            WHERE `order`.id IN (:ids)
            AND `order`.version_id = :versionId
            GROUP BY `order`.id
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['order_number'] ?? '',
                $row['email'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['company'] ?? '',
                $row['customer_number'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
                $row['documentNumber'] ?? '',
                $row['amount_total'] ?? '',
                $row['tracking_codes'] ?? '',
                $id,
            ]));

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                ];

                continue;
            }

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'orderNumber' => $row['order_number'] ?? null,
                'amountTotal' => isset($row['amount_total']) ? (float) $row['amount_total'] : null,
                'orderDateTime' => $this->formatDateTime($row, 'order_date_time'),
                'stateId' => $row['stateId'] ?? null,
                'salesChannelId' => $row['salesChannelId'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
