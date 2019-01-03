<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressPageRequestEvent extends Event
{
    public const NAME = 'accountaddress.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountAddressPageRequest
     */
    protected $accountaddressPageRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountAddressPageRequest $accountaddressPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountaddressPageRequest = $accountaddressPageRequest;
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

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getAccountAddressPageRequest(): AccountAddressPageRequest
    {
        return $this->accountaddressPageRequest;
    }
}
