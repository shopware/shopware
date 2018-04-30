<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Context\Struct\ApplicationContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Shopware\Storefront\Page\Listing\ListingPageStruct;

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
     * @var ListingPageRequest
     */
    protected $request;

    public function __construct(ListingPageStruct $page, StorefrontContext $context, ListingPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context->getApplicationContext();
    }

    public function getStorefrontContext(): StorefrontContext
    {
        return $this->context;
    }

    public function getPage(): ListingPageStruct
    {
        return $this->page;
    }

    public function getRequest(): ListingPageRequest
    {
        return $this->request;
    }
}
