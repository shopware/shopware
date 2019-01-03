<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountRegistration;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class RegistrationPageletRequestEvent extends NestedEvent
{
    public const NAME = 'registration.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RegistrationPageletRequest
     */
    protected $registrationPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, RegistrationPageletRequest $registrationPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->registrationPageletRequest = $registrationPageletRequest;
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

    public function getRegistrationPageletRequest(): RegistrationPageletRequest
    {
        return $this->registrationPageletRequest;
    }
}
