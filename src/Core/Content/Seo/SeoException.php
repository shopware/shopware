<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class SeoException extends HttpException
{
    public const SALES_CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__SALES_CHANNEL_ID_PARAMETER_IS_MISSING';
    public const TEMPLATE_PARAMETER_IS_MISSING = 'FRAMEWORK__TEMPLATE_PARAMETER_IS_MISSING';
    public const ROUTE_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ROUTE_NAME_PARAMETER_IS_MISSING';
    public const ENTITY_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ENTITY_NAME_PARAMETER_IS_MISSING';
    public const SALES_CHANNEL_NOT_FOUND = 'FRAMEWORK__SALES_CHANNEL_NOT_FOUND';

    public static function invalidSalesChannelId(string $salesChannelId): ShopwareHttpException
    {
        return new InvalidSalesChannelIdException($salesChannelId);
    }

    public static function salesChannelIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_ID_PARAMETER_IS_MISSING,
            'Parameter "salesChannelId" is missing.',
        );
    }

    public static function templateParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_PARAMETER_IS_MISSING,
            'Parameter "template" is missing.',
        );
    }

    public static function entityNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENTITY_NAME_PARAMETER_IS_MISSING,
            'Parameter "entityName" is missing.',
        );
    }

    public static function routeNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_NAME_PARAMETER_IS_MISSING,
            'Parameter "routeName" is missing.',
        );
    }

    public static function salesChannelNotFound(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sales channel', 'field' => 'id', 'value' => $salesChannelId]
        );
    }

    public static function seoUrlRouteNotFound(string $routeName): ShopwareHttpException
    {
        return new SeoUrlRouteNotFoundException($routeName);
    }

    public static function noEntitiesForPreview(string $entityName, string $routeName): ShopwareHttpException
    {
        return new NoEntitiesForPreviewException($entityName, $routeName);
    }

    public static function invalidTemplate(string $message): ShopwareHttpException
    {
        return new InvalidTemplateException($message);
    }
}
