<?php declare(strict_types=1);

namespace Shopware\Core;

use Symfony\Component\HttpFoundation\Request;

final class SalesChannelRequest
{
    public const ATTRIBUTE_IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';

    public const ATTRIBUTE_THEME_ID = 'theme-id';
    public const ATTRIBUTE_THEME_NAME = 'theme-name';
    public const ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = 'sw-domain-id';
    public const ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = 'sw-currency-id';

    public const ATTRIBUTE_CANONICAL_LINK = 'sw-canonical-link';

    private function __construct()
    {
    }

    public static function duplicateAttributes(Request $from, Request $to): void
    {
        if ($from->attributes->has(self::ATTRIBUTE_THEME_ID)) {
            $to->attributes->set(self::ATTRIBUTE_THEME_ID, $from->attributes->get(self::ATTRIBUTE_THEME_ID));
        }

        if ($from->attributes->has(self::ATTRIBUTE_THEME_NAME)) {
            $to->attributes->set(self::ATTRIBUTE_THEME_NAME, $from->attributes->get(self::ATTRIBUTE_THEME_NAME));
        }

        if ($from->attributes->has(self::ATTRIBUTE_THEME_BASE_NAME)) {
            $to->attributes->set(self::ATTRIBUTE_THEME_BASE_NAME, $from->attributes->get(self::ATTRIBUTE_THEME_BASE_NAME));
        }

        if ($from->attributes->has(self::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID)) {
            $to->attributes->set(self::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $from->attributes->get(self::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
        }

        if ($from->attributes->has(self::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
            $to->attributes->set(self::ATTRIBUTE_DOMAIN_CURRENCY_ID, $from->attributes->get(self::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        }

        if ($from->attributes->has(self::ATTRIBUTE_CANONICAL_LINK)) {
            $to->attributes->set(self::ATTRIBUTE_CANONICAL_LINK, $from->attributes->get(self::ATTRIBUTE_CANONICAL_LINK));
        }

        if ($from->attributes->has(self::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            $to->attributes->set(self::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, $from->attributes->get(self::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
        }
    }
}
