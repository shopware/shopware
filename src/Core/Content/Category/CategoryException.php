<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class CategoryException extends HttpException
{
    public const SERVICE_CATEGORY_NOT_FOUND = 'CHECKOUT__SERVICE_CATEGORY_NOT_FOUND';
    public const FOOTER_CATEGORY_NOT_FOUND = 'CHECKOUT__FOOTER_CATEGORY_NOT_FOUND';
    public const AFTER_CATEGORY_NOT_FOUND = 'CONTENT__AFTER_CATEGORY_NOT_FOUND';

    public static function pageNotFound(string $pageId): ShopwareHttpException
    {
        return new PageNotFoundException($pageId);
    }

    public static function categoryNotFound(string $id): ShopwareHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function serviceCategoryNotFoundForSalesChannel(string $salesChannelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_CATEGORY_NOT_FOUND,
            'Service category, for sales channel {{ salesChannelName }}, is not set',
            ['salesChannelName' => $salesChannelName]
        );
    }

    public static function footerCategoryNotFoundForSalesChannel(string $salesChannelName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FOOTER_CATEGORY_NOT_FOUND,
            'Footer category, for sales channel {{ salesChannelName }}, is not set',
            ['salesChannelName' => $salesChannelName]
        );
    }

    public static function afterCategoryNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::AFTER_CATEGORY_NOT_FOUND,
            'Category to insert after not found.',
        );
    }
}
