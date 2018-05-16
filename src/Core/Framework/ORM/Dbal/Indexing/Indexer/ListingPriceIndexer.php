<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Indexer;

use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Content\Product\ProductRepository;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Dbal\Indexing\Common\EventIdExtractor;
use Shopware\Framework\ORM\Dbal\Indexing\Common\RepositoryIterator;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressAdvancedEvent;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressFinishedEvent;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressStartedEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ContextPriceStruct;
use Shopware\Framework\ORM\Field\ContextPricesJsonField;
use Shopware\Content\Product\Struct\PriceStruct;
use Shopware\Framework\Struct\Uuid;

class ListingPriceIndexer implements IndexerInterface
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
            new ProgressStartedEvent('Start indexing listing prices', $iterator->getTotal())
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
            new ProgressFinishedEvent('Finished indexing listing prices')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, ApplicationContext $context): void
    {
        if (empty($ids)) {
            return;
        }

        $prices = $this->fetchPrices($ids, $context);

        $field = new ContextPricesJsonField('tmp', 'tmp');

        foreach ($prices as $id => $productPrices) {
            $productPrices = $this->convertPrices($productPrices);
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'contextRuleId')));
            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                $listingPrices[] = $this->findCheapestRulePrice($productPrices, $ruleId);
            }

            $listingPrices = $field->convertToStorage($listingPrices);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id AND tenant_id = :tenant',
                [
                    'price' => json_encode($listingPrices),
                    'id' => Uuid::fromStringToBytes($id),
                    'tenant' => Uuid::fromHexToBytes($context->getTenantId()),
                ]
            );
        }
    }

    private function fetchPrices(array $ids, ApplicationContext $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'IFNULL(HEX(product.parent_id), HEX(product.id)) as id',
            'price.id as price_id',
            'product.id as variant_id',
            'price.context_rule_id',
            'price.price',
            'price.currency_id',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_context_price', 'price', 'price.product_id = product.id AND product.tenant_id = price.tenant_id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('price.quantity_end IS NULL');
        $query->andWhere('price.tenant_id = :tenant');

        $ids = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('tenant', Uuid::fromHexToBytes($context->getTenantId()));

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP);
    }

    /**
     * @param $productPrices
     *
     * @return array
     */
    private function convertPrices($productPrices): array
    {
        $productPrices = array_map(
            function (array $price) {
                $value = json_decode($price['price'], true);
                $value['_class'] = PriceStruct::class;

                return [
                    'id' => Uuid::fromBytesToHex($price['price_id']),
                    'variantId' => Uuid::fromBytesToHex($price['variant_id']),
                    'contextRuleId' => Uuid::fromBytesToHex($price['context_rule_id']),
                    'currencyId' => Uuid::fromBytesToHex($price['currency_id']),
                    'price' => $value,
                    '_class' => ContextPriceStruct::class,
                ];
            },
            $productPrices
        );

        return $productPrices;
    }

    private function findCheapestRulePrice(array $productPrices, string $ruleId): array
    {
        $rulePrices = array_filter(
            $productPrices,
            function (array $price) use ($ruleId) {
                return $price['contextRuleId'] === $ruleId;
            }
        );

        usort(
            $rulePrices,
            function (array $a, array $b) {
                return $a['price']['gross'] <=> $b['price']['gross'];
            }
        );

        return array_shift($rulePrices);
    }
}
