<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;

class AccountOverviewPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-overview.page.loaded';

    /**
     * @var AccountOverviewPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountOverviewPageRequest
     */
    protected $request;

    public function __construct(AccountOverviewPageStruct $page, CheckoutContext $context, AccountOverviewPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new AccountProfilePageletLoadedEvent($this->page->getAccountProfile(), $this->context, $this->request->getAccountProfileRequest()),
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPage(): AccountOverviewPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountOverviewPageRequest
    {
        return $this->request;
    }
}
