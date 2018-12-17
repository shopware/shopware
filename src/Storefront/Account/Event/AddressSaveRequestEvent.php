<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Account\Page\AddressSaveRequest;
use Symfony\Component\HttpFoundation\Request;

class AddressSaveRequestEvent extends NestedEvent
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
     * @var AddressSaveRequest
     */
    private $addressSaveRequest;

    public function __construct(Request $request, CheckoutContext $context, AddressSaveRequest $addressSaveRequest)
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

    public function getAddressSaveRequest(): AddressSaveRequest
    {
        return $this->addressSaveRequest;
    }
}
