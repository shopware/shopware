<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CheckoutPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CheckoutPaymentMethodPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'checkout-paymentmethod.pagelet.loaded.event';

    /**
     * @var CheckoutPaymentMethodPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var CheckoutPaymentMethodPageletRequest
     */
    protected $request;

    public function __construct(
        CheckoutPaymentMethodPageletStruct $pagelet,
        CheckoutContext $context,
        CheckoutPaymentMethodPageletRequest $request
    ) {
        $this->pagelet = $pagelet;
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

    public function getPagelet(): CheckoutPaymentMethodPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): CheckoutPaymentMethodPageletRequest
    {
        return $this->request;
    }
}
