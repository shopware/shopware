<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class SearchKeywordUpdater implements ResetInterface
{
    /**
     * @var array[]
     */
    private array $config = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $productRepository,
        private readonly ProductSearchKeywordAnalyzerInterface $analyzer
    ) {
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('salesChannelDomains.id', null)]));
        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $languages = $this->sortLanguages($languages);

        $products = [];
        foreach ($languages as $language) {
            $languageContext = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]),
                $context->getVersionId()
            );

            $existingProducts = $products[$language->getParentId() ?? Defaults::LANGUAGE_SYSTEM] ?? [];

            $products[$language->getId()] = $this->updateLanguage($ids, $languageContext, $existingProducts);
        }
    }

    public function reset(): void
    {
        $this->config = [];
    }

    /**
     * @return ProductEntity[]
     */
    private function updateLanguage(array $ids, Context $context, array $existingProducts): array
    {
        $configFields = $this->getConfigFields($context->getLanguageId());

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->delete($ids, $context->getLanguageId(), $context->getVersionId());

        $keywords = [];
        $dictionary = [];

        $iterator = $this->getIterator($ids, $context, $configFields);

        while ($products = $iterator->fetch()) {
            /** @var ProductEntity $product */
            foreach ($products as $product) {
                // overwrite fetched products if translations for that product exists
                // otherwise we use the already fetched product from the parent language
                $existingProducts[$product->getId()] = $product;
            }
        }

        foreach ($existingProducts as $product) {
            $analyzed = $this->analyzer->analyze($product, $context, $configFields);

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
                $key = $keyword->getKeyword() . $languageId;
                $dictionary[$key] = [
                    'id' => Uuid::randomBytes(),
                    'language_id' => $languageId,
                    'keyword' => $keyword->getKeyword(),
                ];
            }
        }

        $this->insertKeywords($keywords);
        $this->insertDictionary($dictionary);

        return $existingProducts;
    }

    private function getIterator(array $ids, Context $context, array $configFields): RepositoryIterator
    {
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids);
        $criteria->setLimit(50);

        $this->buildCriteria(array_column($configFields, 'field'), $criteria, $context);

        return new RepositoryIterator($this->productRepository, $context, $criteria);
    }

    private function delete(array $ids, string $languageId, string $versionId): void
    {
        $bytes = Uuid::fromHexToBytesList($ids);

        $params = [
            'ids' => $bytes,
            'language' => Uuid::fromHexToBytes($languageId),
            'versionId' => Uuid::fromHexToBytes($versionId),
        ];

        RetryableQuery::retryable($this->connection, function () use ($params): void {
            $this->connection->executeStatement(
                'DELETE FROM product_search_keyword WHERE product_id IN (:ids) AND language_id = :language AND version_id = :versionId',
                $params,
                ['ids' => ArrayParameterType::STRING]
            );
        });
    }

    private function insertKeywords(array $keywords): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50, true);
        foreach ($keywords as $insert) {
            $queue->addInsert(ProductSearchKeywordDefinition::ENTITY_NAME, $insert);
        }
        $queue->execute();
    }

    private function insertDictionary(array $dictionary): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50, true, true);

        foreach ($dictionary as $insert) {
            $queue->addInsert(ProductKeywordDictionaryDefinition::ENTITY_NAME, $insert);
        }
        $queue->execute();
    }

    private function buildCriteria(array $accessors, Criteria $criteria, Context $context): void
    {
        $definition = $this->productRepository->getDefinition();

        // Filter for products that have translations in the given language
        // if there are no translations, we copy the keywords of the parent language without fetching the product
        $filters = [
            new EqualsFilter('translations.languageId', $context->getLanguageId()),
            new EqualsFilter('parent.translations.languageId', $context->getLanguageId()),
        ];

        foreach ($accessors as $accessor) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

            $fields = array_filter($fields, fn (Field $field) => $field instanceof AssociationField);

            if (empty($fields)) {
                continue;
            }

            $lastAssociationField = $fields[\count($fields) - 1];

            $path = array_map(fn (Field $field) => $field->getPropertyName(), $fields);

            $association = implode('.', $path);
            if ($criteria->hasAssociation($association)) {
                continue;
            }

            $criteria->addAssociation($association);

            $translationField = $lastAssociationField->getReferenceDefinition()->getTranslationField();
            if (!$translationField) {
                continue;
            }

            // filter the associations that have no translations in given language,
            // as we automatically use the parent languages keywords for those
            $translationLanguageAccessor = sprintf(
                '%s.%s.languageId',
                $association,
                $translationField->getPropertyName()
            );
            $filters[] = new EqualsFilter($translationLanguageAccessor, $context->getLanguageId());
        }

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));
    }

    private function getConfigFields(string $languageId): array
    {
        if (isset($this->config[$languageId])) {
            return $this->config[$languageId];
        }

        $query = $this->connection->createQueryBuilder();
        $query->select('configField.field', 'configField.tokenize', 'configField.ranking', 'LOWER(HEX(config.language_id)) as language_id');
        $query->from('product_search_config', 'config');
        $query->join('config', 'product_search_config_field', 'configField', 'config.id = configField.product_search_config_id');
        $query->andWhere('config.language_id IN (:languageIds)');
        $query->andWhere('configField.searchable = 1');

        $query->setParameter('languageIds', Uuid::fromHexToBytesList([$languageId, Defaults::LANGUAGE_SYSTEM]), ArrayParameterType::STRING);

        $all = $query->executeQuery()->fetchAllAssociative();

        $fields = array_filter($all, fn (array $field) => $field['language_id'] === $languageId);

        if (!empty($fields)) {
            return $this->config[$languageId] = $fields;
        }

        $fields = array_filter($all, fn (array $field) => $field['language_id'] === Defaults::LANGUAGE_SYSTEM);

        return $this->config[$languageId] = $fields;
    }

    /**
     * Sort languages so default language comes first, then languages that don't inherit and last inherited languages
     *
     * @return LanguageEntity[]
     */
    private function sortLanguages(LanguageCollection $languages): array
    {
        $defaultLanguage = $languages->get(Defaults::LANGUAGE_SYSTEM);
        $languages->remove(Defaults::LANGUAGE_SYSTEM);

        return array_filter(array_merge(
            [$defaultLanguage],
            $languages->filterByProperty('parentId', null)->getElements(),
            $languages->filter(fn (LanguageEntity $language) => $language->getParentId() !== null)->getElements()
        ));
    }
}
