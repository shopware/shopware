<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class LoginPageRequestEvent extends Event
{
    public const NAME = 'login.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var LoginPageRequest
     */
    protected $loginPageRequest;

    public function __construct(Request $request, CheckoutContext $context, LoginPageRequest $loginPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->loginPageRequest = $loginPageRequest;
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

    public function getLoginPageRequest(): LoginPageRequest
    {
        return $this->loginPageRequest;
    }
}
