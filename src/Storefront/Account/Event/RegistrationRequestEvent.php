<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Account\Page\RegistrationRequest;
use Symfony\Component\HttpFoundation\Request;

class RegistrationRequestEvent extends NestedEvent
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
     * @var RegistrationRequest
     */
    private $registrationRequest;

    public function __construct(Request $request, CheckoutContext $context, RegistrationRequest $registrationRequest)
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

    public function getRegistrationRequest(): RegistrationRequest
    {
        return $this->registrationRequest;
    }
}
