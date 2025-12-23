<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
enum SalesChannelRequestEnum: string
{
    case ATTRIBUTE_IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';

    case ATTRIBUTE_THEME_ID = 'theme-id';
    case ATTRIBUTE_THEME_NAME = 'theme-name';
    case ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    case ATTRIBUTE_SALES_CHANNEL_MAINTENANCE = 'sw-maintenance';

    case ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST = 'sw-maintenance-ip-whitelist';

    /**
     * domain-resolved attributes
     */
    case ATTRIBUTE_DOMAIN_ID = 'sw-domain-id';
    case ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    case ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    case ATTRIBUTE_DOMAIN_CURRENCY_ID = 'sw-currency-id';

    case ATTRIBUTE_CANONICAL_LINK = 'sw-canonical-link';

    case ATTRIBUTE_STOREFRONT_URL = 'sw-storefront-url';
}
