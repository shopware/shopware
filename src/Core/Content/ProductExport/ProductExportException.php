<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport;

use Shopware\Core\Content\ProductExport\Exception\RenderFooterException;
use Shopware\Core\Content\ProductExport\Exception\RenderHeaderException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductExportException extends HttpException
{
    public const TEMPLATE_BODY_NOT_SET = 'PRODUCT_EXPORT__TEMPLATE_BODY_NOT_SET';

    public const RENDER_FOOTER_EXCEPTION = 'PRODUCT_EXPORT__RENDER_FOOTER_EXCEPTION';

    public const RENDER_HEADER_EXCEPTION = 'PRODUCT_EXPORT__RENDER_HEADER_EXCEPTION';

    public const RENDER_PRODUCT_EXCEPTION = 'PRODUCT_EXPORT__RENDER_PRODUCT_EXCEPTION';

    public static function templateBodyNotSet(): ProductExportException
    {
        return new self(Response::HTTP_BAD_REQUEST, self::TEMPLATE_BODY_NOT_SET, 'Template body not set');
    }

    public static function renderFooterException(string $message): ShopwareHttpException
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new RenderFooterException($message);
        }

        return new self(Response::HTTP_BAD_REQUEST, self::RENDER_FOOTER_EXCEPTION, self::getErrorMessage($message));
    }

    public static function renderHeaderException(string $message): ShopwareHttpException
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new RenderHeaderException($message);
        }

        return new self(Response::HTTP_BAD_REQUEST, self::RENDER_HEADER_EXCEPTION, self::getErrorMessage($message));
    }

    public static function renderProductException(string $message): ShopwareHttpException
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new RenderProductException($message);
        }

        return new self(Response::HTTP_BAD_REQUEST, self::RENDER_PRODUCT_EXCEPTION, self::getErrorMessage($message));
    }

    private static function getErrorMessage(string $message): string
    {
        return sprintf('Failed rendering string template using Twig: %s', $message);
    }
}
