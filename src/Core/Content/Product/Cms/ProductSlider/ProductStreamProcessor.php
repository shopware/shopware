<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\ProductSlider;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
class ProductStreamProcessor extends AbstractProductSliderProcessor
{
    private const FALLBACK_LIMIT = 50;

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
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
        $criteria->addFilter(...$filters);
        $criteria->setLimit($limit);

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
        $finalProductIds = $this->collectFinalProductIds($streamResult);
        if (\count($finalProductIds) === 0) {
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
    private function collectFinalProductIds(ProductCollection $streamResult): array
    {
        $finalProductIds = [];
        foreach ($streamResult as $product) {
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

    private function addRandomSort(Criteria $criteria): void
    {
        $fields = [
            'id',
            'stock',
            'releaseDate',
            'manufacturer.id',
            'unit.id',
            'tax.id',
            'cover.id',
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
