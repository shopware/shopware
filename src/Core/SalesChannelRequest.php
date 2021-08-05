<?php declare(strict_types=1);

namespace Shopware\Core;

final class SalesChannelRequest
{
    public const ATTRIBUTE_IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';

    public const ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE = 'allow_maintenance';

    public const ATTRIBUTE_THEME_ID = 'theme-id';
    public const ATTRIBUTE_THEME_NAME = 'theme-name';
    public const ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    public const ATTRIBUTE_SALES_CHANNEL_MAINTENANCE = 'sw-maintenance';

    public const ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST = 'sw-maintenance-ip-whitelist';

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = 'sw-domain-id';
    public const ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = 'sw-currency-id';

    public const ATTRIBUTE_CANONICAL_LINK = 'sw-canonical-link';

    public const ATTRIBUTE_STOREFRONT_URL = 'sw-storefront-url';

    public const ATTRIBUTE_CSRF_PROTECTED = 'csrf_protected';

    private function __construct()
    {
    }
}
