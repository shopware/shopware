<?php declare(strict_types=1);

namespace Shopware\Storefront\Action\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginRequestEvent extends NestedEvent
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
     * @var AccountLoginRequest
     */
    private $loginRequest;

    public function __construct(Request $request, CheckoutContext $context, AccountLoginRequest $loginRequest)
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

    public function getLoginRequest(): AccountLoginRequest
    {
        return $this->loginRequest;
    }
}
