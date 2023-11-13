<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('sales-channel')]
class ProductPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.detail.page';
    final public const DEFAULT_TEMPLATE = '{{ product.translated.name }}/{{ product.productNumber }}';

    /**
     * @internal
     */
    public function __construct(private readonly ProductDefinition $productDefinition)
    {
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

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannel->getId()));
    }

    public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$product instanceof ProductEntity && !$product instanceof PartialEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        $productJson = $product->jsonSerialize();

        return new SeoUrlMapping(
            $product,
            ['productId' => $product->getId()],
            [
                'product' => $productJson,
            ]
        );
    }
}
