<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CheckoutPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class CheckoutPaymentMethodPageletRequestEvent extends NestedEvent
{
    public const NAME = 'checkout-paymentmethod.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CheckoutPaymentMethodPageletRequest
     */
    protected $checkoutPaymentMethodPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, CheckoutPaymentMethodPageletRequest $checkoutPaymentMethodPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->checkoutPaymentMethodPageletRequest = $checkoutPaymentMethodPageletRequest;
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

    public function getCheckoutPaymentMethodPageletRequest(): CheckoutPaymentMethodPageletRequest
    {
        return $this->checkoutPaymentMethodPageletRequest;
    }
}
