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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
class ProductStreamProcessor extends AbstractProductSliderProcessor
{
    private const FALLBACK_LIMIT = 50;

    /**
     * @internal
     */
    public function __construct(
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
    ) {
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

        ProductSliderCriteriaHelper::addGrouping($criteria);
        $sorting = $elementConfig->get('productStreamSorting')?->getStringValue() ?? 'name:' . FieldSorting::ASCENDING;

        if ($sorting === 'random') {
            ProductSliderCriteriaHelper::addRandomSort($criteria);
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
        \assert($products instanceof ProductCollection);
        $products->sortByIdArray($finalProductIds);

        return $products;
    }

    /**
     * @return string[] List of product ids
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

        return array_unique($finalProductIds);
    }
}
