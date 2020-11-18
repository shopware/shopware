<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ProductPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.detail.page';
    public const DEFAULT_TEMPLATE = '{{ product.translated.name }}/{{ product.productNumber }}';

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

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

    public function prepareCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('mainCategories.category');
        $criteria->addAssociation('categories');
    }

    public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$product instanceof ProductEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        $productJson = $product->jsonSerialize();

        $mainCategory = $this->extractMainCategory($product, $salesChannel);
        if ($mainCategory !== null) {
            $productJson['mainCategory'] = $mainCategory->jsonSerialize();
        }

        return new SeoUrlMapping(
            $product,
            ['productId' => $product->getId()],
            [
                'product' => $productJson,
            ]
        );
    }

    private function extractMainCategory(ProductEntity $product, ?SalesChannelEntity $salesChannel): ?CategoryEntity
    {
        $mainCategory = null;
        if ($salesChannel !== null) {
            $mainCategoryEntity = $product->getMainCategories()->filterBySalesChannelId($salesChannel->getId())->first();
            $mainCategory = $mainCategoryEntity !== null ? $mainCategoryEntity->getCategory() : null;
        }

        if ($mainCategory === null) {
            $mainCategory = $product->getCategories() ? $product->getCategories()->sortByPosition()->first() : null;
        }

        return $mainCategory;
    }
}
