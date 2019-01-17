<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginPageRequestEvent extends NestedEvent
{
    public const NAME = 'account-login.page.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var AccountLoginPageRequest
     */
    protected $pageRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, AccountLoginPageRequest $pageRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageRequest = $pageRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getHeaderRequest()),
            new AccountLoginPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getAccountLoginRequest()),
        ]);
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

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function getAccountLoginPageRequest(): AccountLoginPageRequest
    {
        return $this->pageRequest;
    }
}
