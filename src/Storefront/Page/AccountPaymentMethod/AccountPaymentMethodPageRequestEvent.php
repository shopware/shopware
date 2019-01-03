<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class AccountPaymentMethodPageRequestEvent extends Event
{
    public const NAME = 'accountPaymentMethod.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountPaymentMethodPageRequest
     */
    protected $accountPaymentMethodPageRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountPaymentMethodPageRequest $accountPaymentMethodPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->accountPaymentMethodPageRequest = $accountPaymentMethodPageRequest;
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

    public function getAccountPaymentMethodPageRequest(): AccountPaymentMethodPageRequest
    {
        return $this->accountPaymentMethodPageRequest;
    }
}
