<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('system-settings')]
final class OrderAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
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
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(order.id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(DISTINCT country_translation.name SEPARATOR " ") as country,
                   GROUP_CONCAT(DISTINCT order_address_select.city SEPARATOR " ") as city,
                   GROUP_CONCAT(DISTINCT order_address_select.street SEPARATOR " ") as street,
                   GROUP_CONCAT(DISTINCT order_address_select.zipcode SEPARATOR " ") as zipcode,
                   GROUP_CONCAT(DISTINCT order_address_select.phone_number SEPARATOR " ") as phone_number,
                   GROUP_CONCAT(DISTINCT order_address_select.additional_address_line1 SEPARATOR " ") as additional_address_line1,
                   GROUP_CONCAT(DISTINCT order_address_select.additional_address_line2 SEPARATOR " ") as additional_address_line2,
                   GROUP_CONCAT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(document_select.config, "$.documentNumber")) SEPARATOR " ") as documentNumber,
                   order_customer_select.first_name,
                   order_customer_select.last_name,
                   order_customer_select.email,
                   order_customer_select.company,
                   order_customer_select.customer_number,
                   `order`.order_number,
                   `order`.amount_total,
                   order_delivery_select.tracking_codes
            FROM `order`
                LEFT JOIN (SELECT DISTINCT id,
                                           order_id,
                                           first_name,
                                           last_name,
                                           email,
                                           company,
                                           customer_number
                           FROM order_customer
                           WHERE order_customer.order_id IN (:ids)) order_customer_select
                    ON `order`.id = order_customer_select.order_id
                LEFT JOIN (SELECT DISTINCT id,
                                           order_id,
                                           country_id,
                                           city,
                                           street,
                                           zipcode,
                                           phone_number,
                                           additional_address_line1,
                                           additional_address_line2
                           FROM order_address
                           WHERE order_address.order_id IN (:ids)) order_address_select
                    ON `order`.id = order_address_select.order_id
                LEFT JOIN country
                    ON order_address_select.country_id = country.id
                LEFT JOIN country_translation
                    ON country.id = country_translation.country_id
                LEFT JOIN order_tag
                    ON `order`.id = order_tag.order_id
                LEFT JOIN tag
                    ON order_tag.tag_id = tag.id
                LEFT JOIN (SELECT DISTINCT id,
                                           order_id,
                                           tracking_codes
                           FROM order_delivery
                           WHERE order_delivery.order_id IN (:ids)) order_delivery_select
                    ON `order`.id = order_delivery_select.order_id
                LEFT JOIN (SELECT DISTINCT id,
                                           order_id,
                                           config
                           FROM document
                           WHERE document.order_id IN (:ids)) document_select
                    ON `order`.id = document_select.order_id
            WHERE order.id IN (:ids) AND `order`.version_id = :versionId
            GROUP BY order.id
        ',
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
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }
}
