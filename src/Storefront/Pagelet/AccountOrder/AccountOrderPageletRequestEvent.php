<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageletRequestEvent extends NestedEvent
{
    public const NAME = 'accountorder.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountOrderPageletRequest
     */
    protected $accountorderPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountOrderPageletRequest $accountorderPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountorderPageletRequest = $accountorderPageletRequest;
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

    public function getAccountOrderPageletRequest(): AccountOrderPageletRequest
    {
        return $this->accountorderPageletRequest;
    }
}
