<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;

class CheckoutFinishPageLoadedEvent extends NestedEvent
{
    public const NAME = 'checkout-finish.page.loaded';

    /**
     * @var CheckoutFinishPage
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(CheckoutFinishPage $page, CheckoutContext $context, InternalRequest $request)
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

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPage(): CheckoutFinishPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
