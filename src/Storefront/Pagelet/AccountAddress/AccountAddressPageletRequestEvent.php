<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressPageletRequestEvent extends NestedEvent
{
    public const NAME = 'account-address.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var AccountAddressPageletRequest
     */
    protected $addressPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountAddressPageletRequest $addressPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->addressPageletRequest = $addressPageletRequest;
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

    public function getAddressPageletRequest(): AccountAddressPageletRequest
    {
        return $this->addressPageletRequest;
    }
}
