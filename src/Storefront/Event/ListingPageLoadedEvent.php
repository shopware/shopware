<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.page.loaded.event';

    /**
     * @var ListingPageStruct
     */
    protected $page;

    /**
     * @var StorefrontContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(ListingPageStruct $page, StorefrontContext $context, Request $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context->getShopContext();
    }

    public function getStorefrontContext(): StorefrontContext
    {
        return $this->context;
    }

    public function getPage(): ListingPageStruct
    {
        return $this->page;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
