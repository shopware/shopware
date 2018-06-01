<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Context;
use Shopware\Content\Product\ProductRepository;
use Shopware\Framework\Event\ProgressAdvancedEvent;
use Shopware\Framework\Event\ProgressFinishedEvent;
use Shopware\Framework\Event\ProgressStartedEvent;
use Shopware\Framework\ORM\Dbal\Common\EventIdExtractor;
use Shopware\Framework\ORM\Dbal\Common\LastIdQuery;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DatasheetJsonIndexer implements IndexerInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \Shopware\Framework\ORM\Dbal\Common\EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->connection = $connection;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = Context::createDefaultContext($tenantId);

        $iterator = $this->createIterator($tenantId);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing datasheets', $iterator->fetchCount())
        );

        while ($ids = $iterator->fetch()) {
            $ids = array_map(function ($id) {
                return Uuid::fromBytesToHex($id);
            }, $ids);

            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing datasheets')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        $this->update($ids, $event->getContext());
    }

    private function update(array $productIds, Context $context): void
    {
        if (empty($productIds)) {
            return;
        }

        $sql = <<<SQL
UPDATE product, product_datasheet SET product.datasheet_ids = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(product_datasheet.configuration_group_option_id)))), ']')
    FROM product_datasheet
    WHERE product_datasheet.product_id = product.datasheet
    AND product_datasheet.product_tenant_id = :tenant
)
WHERE product_datasheet.product_id = product.datasheet
AND product.tenant_id = :tenant
AND product.id IN (:ids)
SQL;

        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $productIds);

        $this->connection->executeUpdate($sql, ['ids' => $bytes, 'tenant' => $tenantId], ['ids' => Connection::PARAM_STR_ARRAY]);
    }

    private function createIterator(string $tenantId): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['product.auto_increment', 'product.id']);
        $query->from('product');
        $query->andWhere('product.tenant_id = :tenantId');
        $query->andWhere('product.auto_increment > :lastId');
        $query->addOrderBy('product.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
