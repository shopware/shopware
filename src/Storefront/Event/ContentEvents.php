<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Storefront\Page\ContentHome\ContentHomePageRequestEvent;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletRequestEvent;

class ContentEvents
{
    /**
     * Fired when a ContentHome Page request comes in and transformed to the ContentHomePageRequest object
     *
     * @Event("ContentHomePageRequestEvent")
     */
    public const CONTENTHOME_PAGE_REQUEST = ContentHomePageRequestEvent::NAME;

    /**
     * Fired when a Index Pagelet request comes in and transformed to the ContentHomePageletRequest object
     *
     * @Event("ContentHomePageletRequestEvent")
     */
    public const CONTENTHOME_PAGELET_REQUEST = ContentHomePageRequestEvent::NAME;

    /**
     * Fired when a Content Header Pagelet request comes in and transformed to the ContentHeaderPageletRequest object
     *
     * @Event("ContentHeaderPageletRequestEvent")
     */
    public const CONTENTHEADER_PAGELET_REQUEST = ContentHeaderPageletRequestEvent::NAME;

    /**
     * Fired when a Content Currency Pagelet request comes in and transformed to the ContentCurrencyPageletRequest object
     *
     * @Event("ContentCurrencyPageletRequestEvent")
     */
    public const CONTENTCURRENCY_PAGELET_REQUEST = ContentCurrencyPageletRequestEvent::NAME;

    /**
     * Fired when a Content Currency Pagelet request comes in and transformed to the ContentCurrencyPageletRequest object
     *
     * @Event("ContentCurrencyPageletRequestEvent")
     */
    public const CONTENTLANGUAGE_PAGELET_REQUEST = ContentLanguagePageletRequestEvent::NAME;
}
