<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Content\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\DbalIndexing\Common\EventIdExtractor;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Framework\Struct\Uuid;

class VariationJsonIndexer implements IndexerInterface
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
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        Connection $connection
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
            new ProgressStartedEvent('Start indexing variations', $iterator->getTotal())
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
            new ProgressFinishedEvent('Finished indexing variations')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        $this->update($ids, $event->getContext());
    }

    private function update(array $productIds, ApplicationContext $context)
    {
        if (empty($productIds)) {
            return;
        }

        $sql = <<<SQL
UPDATE product, product_variation SET product.variation_ids = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(product_variation.configuration_group_option_id)))), ']')
    FROM product_variation
    WHERE product_variation.product_id = product.id
    AND product_variation.product_tenant_id = :tenant
)
WHERE product_variation.product_id = product.id
AND product.tenant_id = :tenant
AND product.id IN (:ids)
SQL;

        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $productIds);

        $this->connection->executeUpdate(
            $sql,
            ['ids' => $bytes, 'tenant' => $tenantId],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }
}
