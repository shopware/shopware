<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;

class AccountAddressPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-address.pagelet.loaded.event';

    /**
     * @var AccountAddressPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(
        AccountAddressPageletStruct $pagelet,
        CheckoutContext $context,
        InternalRequest $request
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

    public function getPagelet(): AccountAddressPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
