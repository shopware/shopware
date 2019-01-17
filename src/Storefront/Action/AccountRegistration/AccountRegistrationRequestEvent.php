<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountRegistrationRequestEvent extends NestedEvent
{
    public const NAME = 'registration.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var AccountRegistrationRequest
     */
    private $registrationRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountRegistrationRequest $registrationRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->registrationRequest = $registrationRequest;
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

    public function getRegistrationRequest(): AccountRegistrationRequest
    {
        return $this->registrationRequest;
    }
}
