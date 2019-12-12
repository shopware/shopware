<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductSearchKeywordIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductSearchKeywordAnalyzerInterface
     */
    private $analyzer;

    /**
     * @var ProductSearchKeywordDefinition
     */
    private $productSearchKeywordDefinition;

    /**
     * @var ProductKeywordDictionaryDefinition
     */
    private $productKeywordDictionaryDefinition;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        Connection $connection,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $productRepository,
        ProductSearchKeywordAnalyzerInterface $analyzer,
        ProductSearchKeywordDefinition $productSearchKeywordDefinition,
        ProductKeywordDictionaryDefinition $productKeywordDictionaryDefinition
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->languageRepository = $languageRepository;
        $this->productRepository = $productRepository;
        $this->analyzer = $analyzer;
        $this->productSearchKeywordDefinition = $productSearchKeywordDefinition;
        $this->productKeywordDictionaryDefinition = $productKeywordDictionaryDefinition;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $context = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
                Defaults::LIVE_VERSION
            );

            $iterator = $this->iteratorFactory->createIterator($this->productRepository->getDefinition());

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent(
                    sprintf('Start indexing product keywords for language %s', $language->getName()),
                    $iterator->fetchCount()
                ),
                ProgressStartedEvent::NAME
            );

            $this->connection->executeUpdate(
                'DELETE FROM product_keyword_dictionary WHERE language_id = :id',
                ['id' => Uuid::fromHexToBytes($language->getId())]
            );

            while ($ids = $iterator->fetch()) {
                $this->update($ids, $context);

                $this->eventDispatcher->dispatch(
                    new ProgressAdvancedEvent(\count($ids)),
                    ProgressAdvancedEvent::NAME
                );
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent(sprintf('Finished indexing product keywords for language %s', $language->getName())),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('id'));

        $languages = $this->languageRepository->search($criteria, Context::createDefaultContext());

        $languages = array_values($languages->getEntities()->getElements());

        $languageOffset = 0;
        $productOffset = null;
        if ($lastId !== null) {
            $languageOffset = $lastId['languageOffset'];
            $productOffset = $lastId['productOffset'];
        }
        if (!isset($languages[$languageOffset])) {
            return null;
        }

        $language = $languages[$languageOffset];
        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION
        );

        $iterator = $this->iteratorFactory->createIterator($this->productRepository->getDefinition(), $productOffset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            ++$languageOffset;

            return [
                'languageOffset' => $languageOffset,
                'productOffset' => null,
            ];
        }

        $this->connection->executeUpdate(
            'DELETE FROM product_search_keyword WHERE product_id IN (:ids) AND language_id = :language',
            ['ids' => Uuid::fromHexToBytesList($ids), 'language' => Uuid::fromHexToBytes($language->getId())],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->update($ids, $context);

        return [
            'languageOffset' => $languageOffset,
            'productOffset' => $iterator->getOffset(),
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $products = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);

        if (!$products) {
            return;
        }

        if ($products instanceof EntityDeletedEvent) {
            $this->delete($products->getIds(), $event->getContext()->getLanguageId(), $event->getContext()->getVersionId());

            return;
        }

        $ids = $products->getIds();

        $children = $this->connection->fetchAll(
            'SELECT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $children = array_column($children, 'id');

        $ids = array_unique(array_merge($children, $ids));

        $this->update($ids, $event->getContext());
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $products = $context->disableCache(function (Context $context) use ($ids) {
            $context->setConsiderInheritance(true);

            return $this->productRepository->search(new Criteria($ids), $context);
        });

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        $insert = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $keywords = $this->analyzer->analyze($product, $context);

            $productId = Uuid::fromHexToBytes($product->getId());

            foreach ($keywords as $keyword) {
                $insert->addInsert(
                    $this->productSearchKeywordDefinition->getEntityName(),
                    [
                        'id' => Uuid::randomBytes(),
                        'version_id' => $versionId,
                        'product_version_id' => $versionId,
                        'language_id' => $languageId,
                        'product_id' => $productId,
                        'keyword' => $keyword->getKeyword(),
                        'ranking' => $keyword->getRanking(),
                        'created_at' => $now,
                    ]
                );

                $insert->addInsert(
                    $this->productKeywordDictionaryDefinition->getEntityName(),
                    [
                        'id' => Uuid::randomBytes(),
                        'language_id' => $languageId,
                        'keyword' => $keyword->getKeyword(),
                    ]
                );
            }
        }

        $this->connection->beginTransaction();

        try {
            $this->delete($ids, $context->getLanguageId(), $context->getVersionId());

            $insert->execute();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }
        // Only commit if transaction not marked as rollback
        if (!$this->connection->isRollbackOnly()) {
            $this->connection->commit();
        } else {
            $this->connection->rollBack();
        }
    }

    public static function getName(): string
    {
        return 'Swag.ProductSearchKeywordIndexer';
    }

    private function delete(array $ids, string $languageId, string $versionId): void
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $this->connection->executeUpdate(
            'DELETE FROM product_search_keyword WHERE product_id IN (:ids) AND language_id = :language AND version_id = :versionId',
            [
                'ids' => $bytes,
                'language' => Uuid::fromHexToBytes($languageId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }
}
