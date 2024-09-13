<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb;

use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.7.0 feature:BREADCRUMB_STORE_API
 */
#[Package('inventory')]
class BreadcrumbException extends CategoryException
{
    public const BREADCRUMB_CATEGORY_NOT_FOUND = 'BREADCRUMB_CATEGORY_NOT_FOUND';

    public static function categoryNotFoundForProduct(string $productId): self
    {
        return new self(
            Response::HTTP_NO_CONTENT,
            self::BREADCRUMB_CATEGORY_NOT_FOUND,
            'The main category for product {{ productId }} is not found',
            ['productId' => $productId]
        );
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
