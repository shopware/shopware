<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageRequestEvent extends Event
{
    public const NAME = 'accountorder.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountOrderPageRequest
     */
    protected $accountorderPageRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountOrderPageRequest $accountorderPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountorderPageRequest = $accountorderPageRequest;
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

    public function getAccountOrderPageRequest(): AccountOrderPageRequest
    {
        return $this->accountorderPageRequest;
    }
}
