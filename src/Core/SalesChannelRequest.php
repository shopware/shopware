<?php declare(strict_types=1);

namespace Shopware\Core;

final class SalesChannelRequest
{
    public const ATTRIBUTE_IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';
    public const ATTRIBUTE_SALES_CHANNEL_REDIRECT = '_sales_channel_seo_redirect';

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = 'x-sw-domain-id';
    public const ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'x-sw-snippet-set-id';
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = 'x-sw-currency-id';

    public const ATTRIBUTE_CANONICAL_LINK = 'x-sw-canonical-link';

    private function __construct()
    {
    }
}
