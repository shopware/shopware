<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Account\LoginRequest;
use Symfony\Component\HttpFoundation\Request;

class LoginRequestEvent extends NestedEvent
{
    public const NAME = 'login.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var LoginRequest
     */
    private $loginRequest;

    public function __construct(Request $request, CheckoutContext $context, LoginRequest $loginRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->loginRequest = $loginRequest;
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

    public function getLoginRequest(): LoginRequest
    {
        return $this->loginRequest;
    }
}
