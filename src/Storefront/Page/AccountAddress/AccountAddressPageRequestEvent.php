<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequestEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountAddressPageRequestEvent extends NestedEvent
{
    public const NAME = 'account-address.page.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var AccountAddressPageRequest
     */
    protected $pageRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, AccountAddressPageRequest $pageRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageRequest = $pageRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new HeaderPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getHeaderRequest()),
            new AddressPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getAddressRequest())
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

    public function getAccountAddressPageRequest(): AccountAddressPageRequest
    {
        return $this->pageRequest;
    }
}
