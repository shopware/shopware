<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressSaveRequestEvent extends NestedEvent
{
    public const NAME = 'address.save.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var AccountAddressSaveRequest
     */
    private $addressSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountAddressSaveRequest $addressSaveRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->addressSaveRequest = $addressSaveRequest;
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

    public function getAddressSaveRequest(): AccountAddressSaveRequest
    {
        return $this->addressSaveRequest;
    }
}
