<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
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
final class CustomerAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $repository
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
        return CustomerDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'customer-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $customerIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'firstName',
            'lastName',
            'email',
            'company',
            'customerNumber',
            'active',
            'groupId',
            'defaultBillingAddressId',
            'defaultShippingAddressId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(CustomerAddressDefinition::ENTITY_NAME, [
            'firstName',
            'lastName',
            'company',
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
        ]);

        if ($addresses !== []) {
            $customerIds = array_merge($customerIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $tags = $event->getPrimaryKeysWithPropertyChange(CustomerTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['customerId'])) {
                $customerIds[] = $pks['customerId'];
            }
        }

        return array_values(array_unique(array_filter($customerIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'email' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'firstName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'lastName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'customerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'company' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'salutationId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'boundSalesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'requestedGroupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'defaultBillingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'defaultShippingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
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
            SELECT LOWER(HEX(customer.id)) as id,
                   tag_agg.tags as tags,
                   tag_agg.tagIds as tagIds,
                   address_agg.country as country,
                   address_agg.address_first_name as address_first_name,
                   address_agg.address_last_name as address_last_name,
                   address_agg.address_company as address_company,
                   address_agg.city as city,
                   address_agg.street as street,
                   address_agg.zipcode as zipcode,
                   address_agg.phone_number as phone_number,
                   address_agg.additional_address_line1 as additional_address_line1,
                   address_agg.additional_address_line2 as additional_address_line2,
                   customer.first_name,
                   customer.last_name,
                   customer.email,
                   customer.company,
                   customer.customer_number,
                   customer.active AS active,
                   customer.affiliate_code AS affiliateCode,
                   customer.campaign_code AS campaignCode,
                   LOWER(HEX(customer.customer_group_id)) AS groupId,
                   LOWER(HEX(customer.salutation_id)) AS salutationId,
                   LOWER(HEX(customer.bound_sales_channel_id)) AS boundSalesChannelId,
                   LOWER(HEX(customer.requested_customer_group_id)) AS requestedGroupId,
                   LOWER(HEX(customer.default_billing_address_id)) AS defaultBillingAddressId,
                   LOWER(HEX(default_billing_address.country_id)) AS defaultBillingAddressCountryId,
                   LOWER(HEX(customer.default_shipping_address_id)) AS defaultShippingAddressId,
                   LOWER(HEX(default_shipping_address.country_id)) AS defaultShippingAddressCountryId,
                   customer.created_at as createdAt
            FROM customer
                LEFT JOIN (
                    SELECT customer_address.customer_id,
                           GROUP_CONCAT(DISTINCT country_translation.name ORDER BY NULL SEPARATOR ' ') as country,
                           GROUP_CONCAT(DISTINCT customer_address.first_name ORDER BY NULL SEPARATOR ' ') as address_first_name,
                           GROUP_CONCAT(DISTINCT customer_address.last_name ORDER BY NULL SEPARATOR ' ') as address_last_name,
                           GROUP_CONCAT(DISTINCT customer_address.company ORDER BY NULL SEPARATOR ' ') as address_company,
                           GROUP_CONCAT(DISTINCT customer_address.city ORDER BY NULL SEPARATOR ' ') as city,
                           GROUP_CONCAT(DISTINCT customer_address.street ORDER BY NULL SEPARATOR ' ') as street,
                           GROUP_CONCAT(DISTINCT customer_address.zipcode ORDER BY NULL SEPARATOR ' ') as zipcode,
                           GROUP_CONCAT(DISTINCT customer_address.phone_number ORDER BY NULL SEPARATOR ' ') as phone_number,
                           GROUP_CONCAT(DISTINCT customer_address.additional_address_line1 ORDER BY NULL SEPARATOR ' ') as additional_address_line1,
                           GROUP_CONCAT(DISTINCT customer_address.additional_address_line2 ORDER BY NULL SEPARATOR ' ') as additional_address_line2
                    FROM customer_address
                    LEFT JOIN country
                        ON customer_address.country_id = country.id
                    LEFT JOIN country_translation
                        ON country.id = country_translation.country_id
                    WHERE customer_address.customer_id IN (:ids)
                    GROUP BY customer_address.customer_id
                ) as address_agg
                    ON address_agg.customer_id = customer.id
                LEFT JOIN customer_address AS default_billing_address
                    ON customer.default_billing_address_id = default_billing_address.id
                LEFT JOIN customer_address AS default_shipping_address
                    ON customer.default_shipping_address_id = default_shipping_address.id
                LEFT JOIN (
                    SELECT customer_tag.customer_id,
                           GROUP_CONCAT(DISTINCT tag.name ORDER BY NULL SEPARATOR ' ') as tags,
                           GROUP_CONCAT(LOWER(HEX(tag.id)) ORDER BY NULL SEPARATOR ' ') as tagIds
                    FROM customer_tag
                    LEFT JOIN tag
                        ON customer_tag.tag_id = tag.id
                    WHERE customer_tag.customer_id IN (:ids)
                    GROUP BY customer_tag.customer_id
                ) as tag_agg
                    ON tag_agg.customer_id = customer.id
            WHERE customer.id IN (:ids)
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['email'] ?? '',
                $row['customer_number'] ?? '',
                $row['company'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['address_first_name'] ?? '',
                $row['address_last_name'] ?? '',
                $row['address_company'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
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
                'active' => (bool) $row['active'],
                'email' => $row['email'] ?? null,
                'firstName' => $row['first_name'] ?? null,
                'lastName' => $row['last_name'] ?? null,
                'customerNumber' => $row['customer_number'] ?? null,
                'company' => $row['company'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'groupId' => $row['groupId'] ?? null,
                'salutationId' => $row['salutationId'] ?? null,
                'boundSalesChannelId' => $row['boundSalesChannelId'] ?? null,
                'requestedGroupId' => $row['requestedGroupId'] ?? null,
                'defaultBillingAddress' => $this->parseAddress($row, 'defaultBillingAddressId', 'defaultBillingAddressCountryId'),
                'defaultShippingAddress' => $this->parseAddress($row, 'defaultShippingAddressId', 'defaultShippingAddressCountryId'),
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, countryId: string}|null
     */
    private function parseAddress(array $row, string $idKey, string $countryIdKey): ?array
    {
        if (!isset($row[$idKey]) || $row[$idKey] === '') {
            return null;
        }

        return [
            'id' => $row[$idKey],
            '_count' => 1,
            'countryId' => $row[$countryIdKey] ?? '',
        ];
    }
}
