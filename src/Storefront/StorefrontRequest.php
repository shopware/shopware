<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\SalesChannelRequest;

/**
 * @deprecated use Shopware\Core\StorefrontRequest instead
 */
final class StorefrontRequest
{
    public const ATTRIBUTE_IS_STOREFRONT_REQUEST = SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST;
    public const ATTRIBUTE_STOREFRONT_REDIRECT = SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_REDIRECT;

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = SalesChannelRequest::ATTRIBUTE_DOMAIN_ID;
    public const ATTRIBUTE_DOMAIN_LOCALE = SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE;
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID;
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID;

    private function __construct()
    {
    }
}
