<?php declare(strict_types=1);

namespace Shopware\Core;

final class StorefrontRequest
{
    public const ATTRIBUTE_IS_STOREFRONT_REQUEST = '_is_storefront';
    public const ATTRIBUTE_STOREFRONT_REDIRECT = '_storefront_seo_redirect';

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
