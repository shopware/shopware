<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

interface RequestInheritableAttributesInterface
{
    public const INHERITABLE_ATTRIBUTE_NAMES = [
        RequestTransformer::SALES_CHANNEL_BASE_URL,
        RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL,
        RequestTransformer::SALES_CHANNEL_RESOLVED_URI,

        PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID,
        SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST,

        SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_ID,

        SalesChannelRequest::ATTRIBUTE_THEME_ID,
        SalesChannelRequest::ATTRIBUTE_THEME_NAME,
        SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME,

        SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK,
    ];

    public function extract(Request $sourceRequest): array;
}
