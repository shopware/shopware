<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb;

use Shopware\Core\Content\Breadcrumb\Exception\CategoryNotFoundForProductException;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('inventory')]
class BreadcrumbException extends CategoryException
{
    public const BREADCRUMB_CATEGORY_NOT_FOUND = 'BREADCRUMB_CATEGORY_NOT_FOUND';

    /**
     * Throwing the custom exception allows to still catch {@see BreadcrumbException} as usual
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: CategoryNotFoundForProductException::class)]
    public static function categoryNotFoundForProduct(string $productId): self
    {
        return new CategoryNotFoundForProductException($productId);
    }

    public static function categoryNotFound(string $id): ShopwareHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function productNotFound(string $id): ShopwareHttpException
    {
        return new ProductNotFoundException($id);
    }
}
