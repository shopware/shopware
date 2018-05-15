<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Content\Catalog\Repository\CatalogRepository;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Application\Language\Repository\LanguageRepository;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Content\Product\ProductRepository;
use Shopware\Content\Product\Struct\ProductSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Dbal\Indexing\Common\IndexTableOperator;
use Shopware\Framework\ORM\Dbal\Indexing\Common\RepositoryIterator;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressAdvancedEvent;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressFinishedEvent;
use Shopware\Framework\ORM\Dbal\Indexing\Event\ProgressStartedEvent;
use Shopware\Framework\ORM\Dbal\Indexing\Indexer\Analyzer\SearchAnalyzerRegistry;
use Shopware\Defaults;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchIndexer implements IndexerInterface
{
    public const TABLE = 'search_keyword';
    public const DOCUMENT_TABLE = 'product_search_keyword';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SearchAnalyzerRegistry
     */
    private $analyzerRegistry;

    /**
     * @var IndexTableOperator
     */
    private $indexTableOperator;

    /**
     * @var LanguageRepository
     */
    private $languageRepository;

    /**
     * @var CatalogRepository
     */
    private $catalogRepository;

    public function __construct(
        Connection $connection,
        ProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SearchAnalyzerRegistry $analyzerRegistry,
        IndexTableOperator $indexTableOperator,
        LanguageRepository $languageRepository,
        CatalogRepository $catalogRepository
    ) {
        $this->connection = $connection;
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->analyzerRegistry = $analyzerRegistry;
        $this->indexTableOperator = $indexTableOperator;
        $this->languageRepository = $languageRepository;
        $this->catalogRepository = $catalogRepository;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $this->indexTableOperator->createTable(self::TABLE, $timestamp);
        $this->indexTableOperator->createTable(self::DOCUMENT_TABLE, $timestamp);

        $table = $this->indexTableOperator->getIndexName(self::TABLE, $timestamp);
        $documentTable = $this->indexTableOperator->getIndexName(self::DOCUMENT_TABLE, $timestamp);

        $this->connection->executeUpdate('ALTER TABLE `' . $table . '` ADD PRIMARY KEY `language_keyword` (`keyword`, `language_id`, `version_id`, `tenant_id`, `language_tenant_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $table . '` ADD INDEX `keyword` (`keyword`, `language_id`, `language_tenant_id`, `tenant_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $table . '` ADD FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->connection->executeUpdate('ALTER TABLE `' . $documentTable . '` ADD PRIMARY KEY `product_shop_keyword` (`id`, `version_id`, `tenant_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $documentTable . '` ADD UNIQUE KEY (`language_id`, `keyword`, `product_id`, `ranking`, `version_id`, `tenant_id`);');
        $this->connection->executeUpdate('ALTER TABLE `' . $documentTable . '` ADD FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->connection->executeUpdate('ALTER TABLE `' . $documentTable . '` ADD FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE');

        $languages = $this->languageRepository->search(new Criteria(), ApplicationContext::createDefaultContext($tenantId));
        $catalogIds = $this->catalogRepository->searchIds(new Criteria(), ApplicationContext::createDefaultContext($tenantId));

        foreach ($languages as $language) {
            $context = new ApplicationContext(
                $tenantId,
                Defaults::APPLICATION,
                $catalogIds->getIds(),
                [],
                Defaults::CURRENCY,
                $language->getId(),
                $language->getParentId(),
                Defaults::LIVE_VERSION
            );
            $this->indexContext($context, $timestamp);
        }

        $this->connection->transactional(function () use ($table, $documentTable, $tenantId) {
            $tenantId = Uuid::fromStringToBytes($tenantId);

            $this->connection->executeUpdate('DELETE FROM ' . self::DOCUMENT_TABLE . ' WHERE tenant_id = :tenant', ['tenant' => $tenantId]);
            $this->connection->executeUpdate('DELETE FROM ' . self::TABLE . ' WHERE tenant_id = :tenant', ['tenant' => $tenantId]);

            $this->connection->executeUpdate('REPLACE INTO ' . self::DOCUMENT_TABLE . ' SELECT * FROM ' . $documentTable);
            $this->connection->executeUpdate('REPLACE INTO ' . self::TABLE . ' SELECT * FROM ' . $table);

            $this->connection->executeUpdate('DROP TABLE ' . $table);
            $this->connection->executeUpdate('DROP TABLE ' . $documentTable);
        });
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $productEvent = $event->getEventByDefinition(ProductDefinition::class);
        if (!$productEvent) {
            return;
        }

        $context = $productEvent->getContext();
        $products = $this->productRepository->readBasic($productEvent->getIds(), $context);

        $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);
        foreach ($products as $product) {
            $keywords = $this->analyzerRegistry->analyze($product, $context);
            $this->updateQueryQueue($queue, $context, $product->getId(), $keywords, self::TABLE, self::DOCUMENT_TABLE);
        }
        $queue->execute();
    }

    public static function stringReverse($keyword)
    {
        $keyword = (string) $keyword;
        $peaces = preg_split('//u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
        $peaces = array_reverse($peaces);

        return implode('', $peaces);
    }

    private function indexContext(ApplicationContext $context, \DateTime $timestamp): void
    {
        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent(
                sprintf('Start analyzing search keywords for application %s', $context->getApplicationId()),
                $iterator->getTotal()
            )
        );

        $table = $this->indexTableOperator->getIndexName(self::TABLE, $timestamp);
        $documentTable = $this->indexTableOperator->getIndexName(self::DOCUMENT_TABLE, $timestamp);

        /** @var ProductSearchResult $products */
        while ($products = $iterator->fetch()) {
            $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);
            foreach ($products as $product) {
                $keywords = $this->analyzerRegistry->analyze($product, $context);
                $this->updateQueryQueue($queue, $context, $product->getId(), $keywords, $table, $documentTable);
            }
            $queue->execute();

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent($products->count())
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent(sprintf('Finished analyzing search keywords for application id %s', $context->getApplicationId()))
        );
    }

    private function updateQueryQueue(
        MultiInsertQueryQueue $queue,
        ApplicationContext $context,
        string $productId,
        array $keywords,
        string $table,
        string $documentTable
    ): void {
        $languageId = Uuid::fromStringToBytes($context->getLanguageId());
        $productId = Uuid::fromStringToBytes($productId);
        $versionId = Uuid::fromStringToBytes($context->getVersionId());
        $tenantId = Uuid::fromStringToBytes($context->getTenantId());

        foreach ($keywords as $keyword => $ranking) {
            $reversed = static::stringReverse($keyword);

            $queue->addInsert($table, [
                'tenant_id' => $tenantId,
                'language_id' => $languageId,
                'language_tenant_id' => $tenantId,
                'version_id' => $versionId,
                'keyword' => $keyword,
                'reversed' => $reversed,
            ]);

            $queue->addInsert($documentTable, [
                'id' => Uuid::uuid4()->getBytes(),
                'tenant_id' => $tenantId,
                'version_id' => $versionId,
                'product_version_id' => $versionId,
                'product_id' => $productId,
                'product_tenant_id' => $tenantId,
                'language_id' => $languageId,
                'language_tenant_id' => $tenantId,
                'keyword' => $keyword,
                'ranking' => $ranking,
            ]);
        }
    }
}
