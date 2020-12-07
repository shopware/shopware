<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;

class SearchKeywordUpdater
{
    /**
     * @var Connection
     */
    private $connection;

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
     * @internal (flag:FEATURE_NEXT_10552)
     *
     * @var EntityRepositoryInterface|null
     */
    private $productSearchConfigRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $productRepository,
        ProductSearchKeywordAnalyzerInterface $analyzer,
        ?EntityRepositoryInterface $productSearchConfigRepository
    ) {
        $this->connection = $connection;
        $this->languageRepository = $languageRepository;
        $this->productRepository = $productRepository;
        $this->analyzer = $analyzer;
        $this->productSearchConfigRepository = $productSearchConfigRepository;
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $languageContext = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
                $context->getVersionId()
            );

            $this->updateLanguage($ids, $languageContext);
        }
    }

    private function updateLanguage(array $ids, Context $context): void
    {
        $products = $context->disableCache(function (Context $context) use ($ids) {
            return $context->enableInheritance(function (Context $context) use ($ids) {
                $criteria = new Criteria($ids);
                $criteria->addAssociation('manufacturer');
                if (Feature::isActive('FEATURE_NEXT_10552')) {
                    $criteria->addAssociation('categories');
                    $criteria->addAssociation('tags');
                    $criteria->addAssociation('properties');
                    $criteria->addAssociation('options');
                }

                return $this->productRepository->search($criteria, $context);
            });
        });

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->delete($ids, $context->getLanguageId(), $context->getVersionId());

        $keywords = [];
        $dictionary = [];

        $configFields = new ProductSearchConfigFieldCollection();

        if (Feature::isActive('FEATURE_NEXT_10552')) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
            $criteria->addAssociation('configFields');

            if ($this->productSearchConfigRepository !== null) {
                $configData = $this->productSearchConfigRepository->search($criteria, $context);

                if ($configData->getEntities()->first() !== null) {
                    /** @var ProductSearchConfigFieldCollection $configFields */
                    $configFields = $configData->getEntities()->first()->getConfigFields();
                }
            }
        }

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            if (Feature::isActive('FEATURE_NEXT_10552')) {
                $analyzed = $this->analyzer->analyzeBaseOnSearchConfig($product, $context, $configFields);
            } else {
                $analyzed = $this->analyzer->analyze($product, $context);
            }

            $productId = Uuid::fromHexToBytes($product->getId());

            foreach ($analyzed as $keyword) {
                $keywords[] = [
                    'id' => Uuid::randomBytes(),
                    'version_id' => $versionId,
                    'product_version_id' => $versionId,
                    'language_id' => $languageId,
                    'product_id' => $productId,
                    'keyword' => $keyword->getKeyword(),
                    'ranking' => $keyword->getRanking(),
                    'created_at' => $now,
                ];
                $dictionary[] = [
                    'id' => Uuid::randomBytes(),
                    'language_id' => $languageId,
                    'keyword' => $keyword->getKeyword(),
                ];
            }
        }

        $this->insertKeywords($keywords);

        $this->insertDictionary($dictionary);
    }

    private function delete(array $ids, string $languageId, string $versionId): void
    {
        $bytes = Uuid::fromHexToBytesList($ids);

        $params = [
            'ids' => $bytes,
            'language' => Uuid::fromHexToBytes($languageId),
            'versionId' => Uuid::fromHexToBytes($versionId),
        ];

        RetryableQuery::retryable(function () use ($params): void {
            $this->connection->executeUpdate(
                'DELETE FROM product_search_keyword WHERE product_id IN (:ids) AND language_id = :language AND version_id = :versionId',
                $params,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });
    }

    private function insertKeywords(array $keywords): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50);
        foreach ($keywords as $insert) {
            $queue->addInsert(ProductSearchKeywordDefinition::ENTITY_NAME, $insert);
        }

        // try batch insert
        try {
            $queue->execute();
        } catch (\Exception $e) {
            // catch deadlock exception and retry with single insert
            $query = new RetryableQuery(
                $this->connection->prepare('
                    INSERT IGNORE INTO `product_search_keyword` (`id`, `version_id`, `product_version_id`, `language_id`, `product_id`, `keyword`, `ranking`, `created_at`)
                    VALUES (:id, :version_id, :product_version_id, :language_id, :product_id, :keyword, :ranking, :created_at)
                ')
            );

            foreach ($keywords as $keyword) {
                $query->execute($keyword);
            }
        }
    }

    private function insertDictionary(array $dictionary): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50, false, true);
        foreach ($dictionary as $insert) {
            $queue->addInsert(ProductKeywordDictionaryDefinition::ENTITY_NAME, $insert);
        }

        // try batch insert
        try {
            $queue->execute();
        } catch (\Exception $e) {
            // catch deadlock exception and retry with single insert
            $query = new RetryableQuery(
                $this->connection->prepare('INSERT IGNORE INTO `product_keyword_dictionary` (`id`, `language_id`, `keyword`) VALUES (:id, :language_id, :keyword)')
            );

            foreach ($dictionary as $insert) {
                $query->execute($insert);
            }
        }
    }
}
