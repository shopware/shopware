<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;

class AccountLoginPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-login.page.loaded';

    /**
     * @var AccountLoginPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountLoginPageRequest
     */
    protected $request;

    public function __construct(AccountLoginPageStruct $page, CheckoutContext $context, AccountLoginPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new AccountLoginPageletLoadedEvent($this->page->getAccountLogin(), $this->context, $this->request->getAccountLoginRequest()),
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

    public function getPage(): AccountLoginPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountLoginPageRequest
    {
        return $this->request;
    }
}
