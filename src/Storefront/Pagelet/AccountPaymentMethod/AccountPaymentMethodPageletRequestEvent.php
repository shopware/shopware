<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageletRequestEvent extends NestedEvent
{
    public const NAME = 'accountPaymentMethod.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountPaymentMethodPageletRequest
     */
    protected $accountPaymentMethodPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountPaymentMethodPageletRequest $accountPaymentMethodPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountPaymentMethodPageletRequest = $accountPaymentMethodPageletRequest;
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

    public function getAccountPaymentMethodPageletRequest(): AccountPaymentMethodPageletRequest
    {
        return $this->accountPaymentMethodPageletRequest;
    }
}
