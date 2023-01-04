<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('sales-channel')]
class ProductPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.detail.page';
    public const DEFAULT_TEMPLATE = '{{ product.translated.name }}/{{ product.productNumber }}';

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @internal
     */
    public function __construct(ProductDefinition $productDefinition)
    {
        $this->productDefinition = $productDefinition;
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->productDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true
        );
    }

    /**
     * @internal (flag:FEATURE_NEXT_13410) make $salesChannel parameter required
     */
    public function prepareCriteria(Criteria $criteria/*, SalesChannelEntity $salesChannel */): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = \func_num_args() === 2 ? func_get_arg(1) : null;

        if (!Feature::isActive('v6.5.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS')) {
            $criteria->addAssociation('mainCategories.category');
            $criteria->addAssociation('categories');
        }

        if ($salesChannel && Feature::isActive('FEATURE_NEXT_13410')) {
            $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannel->getId()));
        }
    }

    public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$product instanceof ProductEntity && !$product instanceof PartialEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        $productJson = $product->jsonSerialize();

        if (!Feature::isActive('v6.5.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS')) {
            $mainCategory = $this->extractMainCategory($product, $salesChannel);
            if ($mainCategory !== null) {
                $productJson['mainCategory'] = $mainCategory->jsonSerialize();
            }
        }

        return new SeoUrlMapping(
            $product,
            ['productId' => $product->getId()],
            [
                'product' => $productJson,
            ]
        );
    }

    /**
     * @deprecated tag:v6.5.0 - Use product.categories.sortByPosition().first.translated.name in the seo url template instead
     */
    private function extractMainCategory(Entity $product, ?SalesChannelEntity $salesChannel): ?CategoryEntity
    {
        $mainCategory = null;
        if ($salesChannel !== null && $product->get('mainCategories') instanceof MainCategoryCollection) {
            $mainCategoryEntity = $product->get('mainCategories')->filterBySalesChannelId($salesChannel->getId())->first();
            $mainCategory = $mainCategoryEntity !== null ? $mainCategoryEntity->getCategory() : null;
        }

        if ($mainCategory === null && $product->get('categories') instanceof CategoryCollection) {
            $mainCategory = $product->get('categories')->sortByPosition()->first();
        }

        return $mainCategory;
    }
}
