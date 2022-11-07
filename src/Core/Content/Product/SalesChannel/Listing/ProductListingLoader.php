<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingPreviewCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingLoader
{
    private SalesChannelRepositoryInterface $repository;

    private SystemConfigService $systemConfigService;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    /**
     * @internal
     */
    public function __construct(
        SalesChannelRepositoryInterface $repository,
        SystemConfigService $systemConfigService,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
        $this->repository = $repository;
        $this->systemConfigService = $systemConfigService;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->productCloseoutFilterFactory = $productCloseoutFilterFactory;
    }

    public function load(Criteria $origin, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = clone $origin;

        $this->addGrouping($criteria);
        $this->handleAvailableStock($criteria, $context);

        $origin->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        if (!Feature::isActive('v6.5.0.0')) {
            $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);
        }

        $ids = $this->repository->searchIds($criteria, $context);

        $aggregations = $this->repository->aggregate($criteria, $context);

        // no products found, no need to continue
        if (empty($ids->getIds())) {
            return new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                0,
                new ProductCollection(),
                $aggregations,
                $origin,
                $context->getContext()
            );
        }

        $mapping = array_combine($ids->getIds(), $ids->getIds());

        $hasOptionFilter = $this->hasOptionFilter($criteria);
        if (!$hasOptionFilter) {
            $mapping = $this->resolvePreviews($ids->getIds(), $context);
        }

        $event = new ProductListingResolvePreviewEvent($context, $criteria, $mapping, $hasOptionFilter);
        $this->eventDispatcher->dispatch($event);
        $mapping = $event->getMapping();

        $read = $criteria->cloneForRead(array_values($mapping));
        $read->addAssociation('options.group');

        $entities = $this->repository->search($read, $context);

        $this->addExtensions($ids, $entities, $mapping);

        $result = new EntitySearchResult(ProductDefinition::ENTITY_NAME, $ids->getTotal(), $entities->getEntities(), $aggregations, $origin, $context->getContext());
        $result->addState(...$ids->getStates());

        return $result;
    }

    private function hasOptionFilter(Criteria $criteria): bool
    {
        $filters = $criteria->getPostFilters();

        $fields = [];
        foreach ($filters as $filter) {
            array_push($fields, ...$filter->getFields());
        }

        $fields = array_map(function (string $field) {
            return preg_replace('/^product./', '', $field);
        }, $fields);

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

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): void
    {
        $salesChannelId = $context->getSalesChannel()->getId();

        $hide = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if (!$hide) {
            return;
        }

        $closeoutFilter = $this->productCloseoutFilterFactory->create($context);
        $criteria->addFilter($closeoutFilter);
    }

    /**
     * @param array<array<string>|string> $ids
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function resolvePreviews(array $ids, SalesChannelContext $context): array
    {
        $ids = array_combine($ids, $ids);

        $config = $this->connection->fetchAll(
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
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $mapping = [];
        foreach ($config as $item) {
            if ($item['variantListingConfig'] === null) {
                continue;
            }
            $variantListingConfig = json_decode($item['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if ($variantListingConfig['mainVariantId']) {
                $mapping[$item['id']] = $variantListingConfig['mainVariantId'];
            }

            if ($variantListingConfig['displayParent']) {
                $mapping[$item['id']] = $item['parentId'];
            }
        }

        // now we have a mapping for "child => main variant"
        if (empty($mapping)) {
            return $ids;
        }

        // filter inactive and not available variants
        $criteria = new Criteria(array_values($mapping));
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannel()->getId()));
        $this->handleAvailableStock($criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductListingPreviewCriteriaEvent($criteria, $context)
        );

        $available = $this->repository->searchIds($criteria, $context);

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
     * @param array<string> $mapping
     */
    private function addExtensions(IdSearchResult $ids, EntitySearchResult $entities, array $mapping): void
    {
        foreach ($ids->getExtensions() as $name => $extension) {
            $entities->addExtension($name, $extension);
        }

        /** @var string $id */
        foreach ($ids->getIds() as $id) {
            if (!isset($mapping[$id])) {
                continue;
            }

            // current id was mapped to another variant
            if (!$entities->has($mapping[$id])) {
                continue;
            }

            /** @var Entity $entity */
            $entity = $entities->get($mapping[$id]);

            // get access to the data of the search result
            $entity->addExtension('search', new ArrayEntity($ids->getDataOfId($id)));
        }
    }
}
