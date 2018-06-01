<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
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
     * @var StorefrontContext
     */
    private $context;

    /**
     * @var LoginRequest
     */
    private $loginRequest;

    public function __construct(Request $request, StorefrontContext $context, LoginRequest $loginRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->loginRequest = $loginRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context->getApplicationContext();
    }

    public function getStorefrontContext(): StorefrontContext
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
