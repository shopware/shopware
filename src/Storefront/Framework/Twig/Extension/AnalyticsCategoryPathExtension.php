<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Content\Product\Cart\ProductCategoryPathResolver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Resolves the category path an analytics integration reports for a product, ordered from the top
 * level down.
 *
 * Product line items carry the path in their payload, but a product that is only rendered as a card
 * does not. The page breadcrumb is no substitute: on a listing it describes the listing category
 * rather than the product, and on the wishlist page it describes the wishlist.
 *
 * The path stays empty unless the loader of the page asked for the `categories` and
 * `mainCategories.category` associations, so a page that does not report categories pays nothing.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsCategoryPathExtension extends AbstractExtension
{
    /**
     * The resolver is stateless and has no dependencies, so it is instantiated instead of injected,
     * the same way {@see \Shopware\Core\Content\Product\Cart\ProductCartProcessor} does it.
     */
    private readonly ProductCategoryPathResolver $categoryPathResolver;

    public function __construct()
    {
        $this->categoryPathResolver = new ProductCategoryPathResolver();
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_analytics_category_path', $this->getPath(...)),
        ];
    }

    /**
     * @return list<string>
     */
    public function getPath(?Entity $product, SalesChannelContext $context): array
    {
        if (!$product instanceof SalesChannelProductEntity) {
            return [];
        }

        return $this->categoryPathResolver->getPath($product, $context);
    }
}
