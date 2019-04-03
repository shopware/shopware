<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CheckoutRegisterPageLoadedEvent extends NestedEvent
{
    public const NAME = 'checkout-register.page.loaded';

    /**
     * @var CheckoutRegisterPage
     */
    protected $page;

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(CheckoutRegisterPage $page, SalesChannelContext $context, InternalRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPage(): CheckoutRegisterPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
