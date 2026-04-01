<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\ProductSlider;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Events\ProductSliderStreamCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class ProductStreamProcessor extends AbstractProductSliderProcessor
{
    private const FALLBACK_LIMIT = 50;

    private const EXPLICIT_PRODUCT_SELECTION_EXTENSION = 'productStreamExplicitProductSelection';

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractProductSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'product_stream';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $products = $config->get('products');
        \assert($products instanceof FieldConfig);
        $criteria = $this->collectByProductStream($resolverContext, $products, $config);

        $this->eventDispatcher->dispatch(new ProductSliderStreamCriteriaEvent($slot, $criteria, $resolverContext->getSalesChannelContext()));

        $collection = new CriteriaCollection();
        $collection->add(self::PRODUCT_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        $entitySearchResult = $result->get(self::PRODUCT_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier());
        if (!$entitySearchResult) {
            return;
        }

        $streamResult = $entitySearchResult->getEntities();
        if (!$streamResult instanceof ProductCollection) {
            return;
        }

        $slider = new ProductSliderStruct();
        $slot->setData($slider);

        $slider->setProducts(
            $this->handleProductStream(
                $streamResult,
                $resolverContext->getSalesChannelContext(),
                $entitySearchResult->getCriteria()
            )
        );

        $config = $slot->getFieldConfig();

        $productConfig = $config->get('products');
        \assert($productConfig instanceof FieldConfig);

        $slider->setStreamId($productConfig->getStringValue());
    }

    private function collectByProductStream(
        ResolverContext $resolverContext,
        FieldConfig $config,
        FieldConfigCollection $elementConfig
    ): Criteria {
        $filters = $this->productStreamBuilder->buildFilters(
            $config->getStringValue(),
            $resolverContext->getSalesChannelContext()->getContext()
        );

        $limit = $elementConfig->get('productStreamLimit')?->getIntValue() ?? self::FALLBACK_LIMIT;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(...$filters);
        $criteria->setLimit($limit);

        $explicitProductIds = $this->resolveExplicitProductIds($filters);
        if ($explicitProductIds !== []) {
            $criteria->addExtension(self::EXPLICIT_PRODUCT_SELECTION_EXTENSION, new ArrayStruct([
                'ids' => $explicitProductIds,
                'criteria' => clone $criteria,
            ]));
        }

        $this->addGrouping($criteria);
        $sorting = $elementConfig->get('productStreamSorting')?->getStringValue() ?? 'name:' . FieldSorting::ASCENDING;

        if ($sorting === 'random') {
            $this->addRandomSort($criteria);
        } else {
            $sorting = explode(':', $sorting);
            $field = $sorting[0];
            $direction = $sorting[1];

            $criteria->addSorting(new FieldSorting($field, $direction));
        }

        return $criteria;
    }

    private function handleProductStream(
        ProductCollection $streamResult,
        SalesChannelContext $context,
        Criteria $originCriteria
    ): ProductCollection {
        $explicitSelection = $originCriteria->getExtensionOfType(self::EXPLICIT_PRODUCT_SELECTION_EXTENSION, ArrayStruct::class);
        $explicitProductIds = $explicitSelection instanceof ArrayStruct && \is_array($explicitSelection->get('ids'))
            ? array_values(array_filter($explicitSelection->get('ids'), \is_string(...)))
            : [];

        $finalProductIds = $this->collectFinalProductIds($streamResult, $explicitProductIds);

        $missingExplicitProductIds = array_values(array_diff($explicitProductIds, $streamResult->getIds()));
        $ungroupedCriteria = $explicitSelection?->get('criteria');
        if ($missingExplicitProductIds !== [] && $ungroupedCriteria instanceof Criteria) {
            $explicitProducts = $this->loadExplicitProducts($ungroupedCriteria, $missingExplicitProductIds, $context);
            $finalProductIds = array_values(array_unique([...$finalProductIds, ...$explicitProducts->getIds()]));
        }

        if ($finalProductIds === []) {
            return new ProductCollection();
        }

        $criteria = $originCriteria->cloneForRead($finalProductIds);

        $products = $this->productRepository->search($criteria, $context)->getEntities();
        $products->sortByIdArray($finalProductIds);

        return $products;
    }

    /**
     * @return list<string>
     */
    private function collectFinalProductIds(ProductCollection $streamResult, array $explicitProductIds = []): array
    {
        $explicitProductIds = array_flip($explicitProductIds);
        $finalProductIds = [];
        foreach ($streamResult as $product) {
            if (isset($explicitProductIds[$product->getId()])) {
                $finalProductIds[] = $product->getId();

                continue;
            }

            $variantConfig = $product->getVariantListingConfig();

            if (!$variantConfig) {
                $finalProductIds[] = $product->getId();
                continue;
            }

            $productId = $variantConfig->getDisplayParent()
                ? $product->getParentId() : $variantConfig->getMainVariantId();

            $finalProductIds[] = $productId ?? $product->getId();
        }

        return array_values(array_unique($finalProductIds));
    }

    /**
     * @param list<string> $ids
     */
    private function loadExplicitProducts(Criteria $criteria, array $ids, SalesChannelContext $context): ProductCollection
    {
        $criteria->setIds($ids);
        $criteria->setOffset(null);
        $criteria->setLimit(null);

        return $this->productRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<Filter> $filters
     *
     * @return list<string>
     */
    private function resolveExplicitProductIds(array $filters): array
    {
        return array_values(array_unique($this->collectExplicitProductIds($filters)));
    }

    /**
     * @param array<Filter> $filters
     *
     * @return list<string>
     */
    private function collectExplicitProductIds(array $filters): array
    {
        $ids = [];

        foreach ($filters as $filter) {
            if ($filter instanceof NotFilter) {
                continue;
            }

            if ($filter instanceof MultiFilter) {
                array_push($ids, ...$this->collectExplicitProductIds($filter->getQueries()));

                continue;
            }

            if ($filter instanceof EqualsFilter && $this->isExplicitProductIdField($filter->getField()) && \is_string($filter->getValue())) {
                $ids[] = $filter->getValue();

                continue;
            }

            if (!$filter instanceof EqualsAnyFilter || !$this->isExplicitProductIdField($filter->getField())) {
                continue;
            }

            foreach ($filter->getValue() as $value) {
                if (\is_string($value)) {
                    $ids[] = $value;
                }
            }
        }

        return $ids;
    }

    private function isExplicitProductIdField(string $field): bool
    {
        return preg_replace('/^product\./', '', $field) === 'id';
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    private function addRandomSort(Criteria $criteria): void
    {
        // these fields should be compatible with Elasticsearch mapped fields for sorting, see: \Shopware\Elasticsearch\Product\ElasticsearchProductDefinition::getMapping
        $fields = [
            'id',
            'stock',
            'releaseDate',
            'manufacturerId',
            'deliveryTimeId',
            'taxId',
            'coverId',
        ];
        shuffle($fields);
        $fields = \array_slice($fields, 0, 2);
        $direction = [FieldSorting::ASCENDING, FieldSorting::DESCENDING];
        $direction = $direction[random_int(0, 1)];

        foreach ($fields as $field) {
            $criteria->addSorting(new FieldSorting($field, $direction));
        }
    }
}
