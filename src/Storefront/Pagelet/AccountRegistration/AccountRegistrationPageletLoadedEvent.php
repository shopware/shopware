<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class AccountRegistrationPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-registration.pagelet.loaded.event';

    /**
     * @var AccountRegistrationPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountRegistrationPageletRequest
     */
    protected $request;

    public function __construct(
        AccountRegistrationPageletStruct $pagelet,
        CheckoutContext $context,
        AccountRegistrationPageletRequest $request
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

    public function getPagelet(): AccountRegistrationPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): AccountRegistrationPageletRequest
    {
        return $this->request;
    }
}
