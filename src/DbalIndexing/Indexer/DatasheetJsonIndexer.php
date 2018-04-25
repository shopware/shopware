<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\DbalIndexing\Common\EventIdExtractor;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Framework\Struct\Uuid;

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
     * @var EventIdExtractor
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
        $context = ApplicationContext::createDefaultContext($tenantId);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing datasheets', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
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

    private function update(array $productIds, ApplicationContext $context): void
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
}
