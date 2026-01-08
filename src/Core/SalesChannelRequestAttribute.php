<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
enum SalesChannelRequestAttribute: string
{
    case IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';

    case THEME_ID = 'theme-id';
    case THEME_NAME = 'theme-name';
    case THEME_BASE_NAME = 'theme-base-name';

    case SALES_CHANNEL_MAINTENANCE = 'sw-maintenance';

    case SALES_CHANNEL_MAINTENANCE_IP_ALLOW_LIST = 'sw-maintenance-ip-whitelist';

    /**
     * domain-resolved attributes
     */
    case DOMAIN_ID = 'sw-domain-id';
    case DOMAIN_LOCALE = '_locale';
    case DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    case DOMAIN_CURRENCY_ID = 'sw-currency-id';

    case CANONICAL_LINK = 'sw-canonical-link';

    case STOREFRONT_URL = 'sw-storefront-url';
}
