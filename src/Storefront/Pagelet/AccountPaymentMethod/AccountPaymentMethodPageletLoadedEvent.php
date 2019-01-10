<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AccountPaymentMethodPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-paymentmethod.pagelet.loaded.event';

    /**
     * @var AccountPaymentMethodPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountPaymentMethodPageletRequest
     */
    protected $request;

    public function __construct(
        AccountPaymentMethodPageletStruct $pagelet,
        CheckoutContext $context,
        AccountPaymentMethodPageletRequest $request
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

    public function getPagelet(): AccountPaymentMethodPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): AccountPaymentMethodPageletRequest
    {
        return $this->request;
    }
}
