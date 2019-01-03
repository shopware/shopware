<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AccountAddressPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account.address.pagel.loaded.event';

    /**
     * @var AccountAddressPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountAddressPageRequest
     */
    protected $request;

    public function __construct(AccountAddressPageStruct $page, CheckoutContext $context, AccountAddressPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
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

    public function getPage(): AccountAddressPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountAddressPageRequest
    {
        return $this->request;
    }
}
