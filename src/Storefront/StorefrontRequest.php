<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\StorefrontRequest as CoreStorefrontRequest;

/**
 * @deprecated use Shopware\Core\StorefrontRequest instead
 */
final class StorefrontRequest
{
    public const ATTRIBUTE_IS_STOREFRONT_REQUEST = CoreStorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST;
    public const ATTRIBUTE_STOREFRONT_REDIRECT = CoreStorefrontRequest::ATTRIBUTE_STOREFRONT_REDIRECT;

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = CoreStorefrontRequest::ATTRIBUTE_DOMAIN_ID;
    public const ATTRIBUTE_DOMAIN_LOCALE = CoreStorefrontRequest::ATTRIBUTE_DOMAIN_LOCALE;
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = CoreStorefrontRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID;
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = CoreStorefrontRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID;

    private function __construct()
    {
    }
}
