<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class LoginPageletRequestEvent extends NestedEvent
{
    public const NAME = 'login.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var LoginPageletRequest
     */
    protected $loginPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, LoginPageletRequest $loginPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->loginPageletRequest = $loginPageletRequest;
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

    public function getLoginPageletRequest(): LoginPageletRequest
    {
        return $this->loginPageletRequest;
    }
}
