<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountProfilePageRequestEvent extends NestedEvent
{
    public const NAME = 'account-profile.page.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var AccountProfilePageRequest
     */
    protected $pageRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, AccountProfilePageRequest $pageRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageRequest = $pageRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getHeaderRequest()),
            new AccountProfilePageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getAccountProfileRequest()),
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

    public function getAccountProfilePageRequest(): AccountProfilePageRequest
    {
        return $this->pageRequest;
    }
}
