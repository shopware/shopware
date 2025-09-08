<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingPreviewCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\Extension\LoadPreviewExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingAggregationsExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingIdsExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductListingLoader
{
    public const ID_CACHE_KEY = 'product-listing-ids-';
    public const AGGREGATIONS_CACHE_KEY = 'product-listing-aggregations-';

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
    ) {
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function load(Criteria $origin, SalesChannelContext $context): EntitySearchResult
    {
        // allows full service decoration
        return $this->extensions->publish(
            name: ResolveListingExtension::NAME,
            extension: new ResolveListingExtension($origin, $context),
            function: $this->_load(...)
        );
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function _load(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $clone = clone $criteria;

        $idResult = $this->extensions->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: new ResolveListingIdsExtension($clone, $context),
            function: $this->resolveIds(...)
        );

        /** @var list<string> */
        $ids = $idResult->getIds();
        // no products found, no need to continue
        if (empty($ids)) {
            $result = new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                0,
                new ProductCollection(),
                null,
                $criteria,
                $context->getContext()
            );

            $result->addState(...$idResult->getStates());
            $result->addExtensions($idResult->getExtensions());

            return $result;
        }

        $aggregations = $this->extensions->publish(
            name: ResolveListingAggregationsExtension::NAME,
            extension: new ResolveListingAggregationsExtension($clone, $context, $idResult),
            function: $this->resolveAggregations(...)
        );

        $mapping = $this->resolvePreviews($ids, $clone, $context);

        $productSearchResult = $this->resolveData($clone, $mapping, $context);

        $this->addExtensions($idResult, $productSearchResult, $mapping);

        $result = new EntitySearchResult(ProductDefinition::ENTITY_NAME, $idResult->getTotal(), $productSearchResult->getEntities(), $aggregations, $criteria, $context->getContext());
        $result->addState(...$idResult->getStates());
        $result->addExtensions($productSearchResult->getExtensions());

        return $result;
    }

    private function hasOptionFilter(Criteria $criteria): bool
    {
        $filters = $criteria->getPostFilters();

        $fields = [];
        foreach ($filters as $filter) {
            array_push($fields, ...$filter->getFields());
        }

        $fields = array_map(fn (string $field) => preg_replace('/^product./', '', $field), $fields);

        if (\in_array('options.id', $fields, true)) {
            return true;
        }

        if (\in_array('optionIds', $fields, true)) {
            return true;
        }

        return false;
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    /**
     * @param array<string> $ids
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function loadPreviews(array $ids, SalesChannelContext $context): array
    {
        $ids = array_combine($ids, $ids);

        $config = $this->connection->fetchAllAssociative(
            '# product-listing-loader::resolve-previews
            SELECT
                parent.variant_listing_config as variantListingConfig,
                LOWER(HEX(child.id)) as id,
                LOWER(HEX(parent.id)) as parentId
             FROM product as child
                INNER JOIN product as parent
                    ON parent.id = child.parent_id
                    AND parent.version_id = child.version_id
             WHERE child.version_id = :version
             AND child.id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $mapping = [];
        foreach ($config as $item) {
            if ($item['variantListingConfig'] === null) {
                continue;
            }
            $variantListingConfig = json_decode((string) $item['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (isset($variantListingConfig['mainVariantId']) && $variantListingConfig['mainVariantId']) {
                $mapping[$item['id']] = $variantListingConfig['mainVariantId'];
            }

            if (isset($variantListingConfig['displayParent']) && $variantListingConfig['displayParent']) {
                $mapping[$item['id']] = $item['parentId'];
            }
        }

        // now we have a mapping for "child => main variant"
        if (empty($mapping)) {
            return $ids;
        }

        // filter inactive and not available variants
        $criteria = new Criteria(array_values($mapping));
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId()));

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(
                $this->productCloseoutFilterFactory->create($context)
            );
        }

        $this->dispatcher->dispatch(
            new ProductListingPreviewCriteriaEvent($criteria, $context)
        );

        $available = $this->productRepository->searchIds($criteria, $context);

        $remapped = [];
        // replace existing ids with main variant id
        foreach ($ids as $id) {
            // id has no mapped main_variant - keep old id
            if (!isset($mapping[$id])) {
                $remapped[$id] = $id;

                continue;
            }

            // get access to main variant id over the fetched config mapping
            $main = $mapping[$id];

            // main variant is configured but not active/available - keep old id
            if (!$available->has($main)) {
                $remapped[$id] = $id;

                continue;
            }

            // main variant is configured and available - add main variant id
            $remapped[$id] = $main;
        }

        return $remapped;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $productSearchResult
     * @param array<string> $mapping
     */
    private function addExtensions(IdSearchResult $ids, EntitySearchResult $productSearchResult, array $mapping): void
    {
        foreach ($ids->getExtensions() as $name => $extension) {
            $productSearchResult->addExtension($name, $extension);
        }

        /** @var string $id */
        foreach ($ids->getIds() as $id) {
            if (!isset($mapping[$id])) {
                continue;
            }

            // current id was mapped to another variant
            if (!$productSearchResult->has($mapping[$id])) {
                continue;
            }

            $product = $productSearchResult->get($mapping[$id]);

            // get access to the data of the search result
            $product->addExtension('search', new ArrayEntity($ids->getDataOfId($id)));
        }
    }

    private function resolveIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $this->addGrouping($criteria);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        )) {
            $criteria->addFilter(
                $this->productCloseoutFilterFactory->create($context)
            );
        }

        $cacheKey = self::ID_CACHE_KEY . $this->generator->getCriteriaHash($criteria) . $this->generator->getSalesChannelContextHash($context);

        $ids = $this->cache->get($cacheKey, function (ItemInterface $item) use ($criteria, $context) {
            $idSearchResult = $this->productRepository->searchIds($criteria, $context);

            /** @var list<string> */
            $ids = $idSearchResult->getIds();

            $cacheTags = \array_map(fn (string $id): string => EntityCacheKeyGenerator::buildProductTag($id), $ids);

            $item->expiresAfter(3600);
            $item->tag($cacheTags);

            // To reduce cache size and to prevent cache issues we clear context and criteria before caching
            // They will be reassigned right after uncompressing the cached value
            $idSearchResult->assign(['context' => Context::createDefaultContext(), 'criteria' => new Criteria()]);

            return CacheValueCompressor::compress($idSearchResult);
        });

        /** @var IdSearchResult */
        $ids = CacheValueCompressor::uncompress($ids);

        $ids->assign(['context' => $context->getContext(), 'criteria' => $criteria]);

        return $ids;
    }

    private function resolveAggregations(Criteria $criteria, SalesChannelContext $context, IdSearchResult $idSearchResult): ?AggregationResultCollection
    {
        $cacheKey = self::AGGREGATIONS_CACHE_KEY . $this->generator->getCriteriaHash($criteria) . $this->generator->getSalesChannelContextHash($context);

        $aggregations = $this->cache->get($cacheKey, function (ItemInterface $item) use ($criteria, $context, $idSearchResult) {
            $aggregationResult = $this->productRepository->aggregate($criteria, $context);

            /** @var list<string> */
            $ids = $idSearchResult->getIds();

            $cacheTags = \array_map(fn (string $id): string => EntityCacheKeyGenerator::buildProductTag($id), $ids);

            $item->expiresAfter(3600);
            $item->tag($cacheTags);

            return CacheValueCompressor::compress($aggregationResult);
        });

        /** @var ?AggregationResultCollection */
        $aggregations = CacheValueCompressor::uncompress($aggregations);

        return $aggregations;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function resolvePreviews(array $keys, Criteria $criteria, SalesChannelContext $context): array
    {
        $mapping = array_combine($keys, $keys);

        $hasOptionFilter = $this->hasOptionFilter($criteria);

        $shouldLoadPreviews = $this->shouldLoadPreviews($hasOptionFilter, $criteria);

        if ($shouldLoadPreviews) {
            $mapping = $this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($keys, $context),
                function: $this->loadPreviews(...)
            );
        }

        $event = new ProductListingResolvePreviewEvent($context, $criteria, $mapping, $hasOptionFilter);
        $this->dispatcher->dispatch($event);

        return $event->getMapping();
    }

    private function shouldLoadPreviews(bool $hasOptionFilter, Criteria $criteria): bool
    {
        if ($hasOptionFilter === true) {
            return false;
        }

        return !$criteria->hasState(ResolvedCriteriaProductSearchRoute::STATE, ProductSuggestRoute::STATE);
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return EntitySearchResult<ProductCollection>
     */
    private function resolveData(Criteria $criteria, array $mapping, SalesChannelContext $context): EntitySearchResult
    {
        $read = $criteria->cloneForRead(array_values($mapping));
        $read->addAssociation('options.group');

        return $this->productRepository->search($read, $context);
    }
}
