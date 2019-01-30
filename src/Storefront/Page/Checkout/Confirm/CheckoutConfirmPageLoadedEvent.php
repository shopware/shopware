<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;

class CheckoutConfirmPageLoadedEvent extends NestedEvent
{
    public const NAME = 'checkout-confirm.page.loaded';

    /**
     * @var CheckoutConfirmPage
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

    public function __construct(CheckoutConfirmPage $page, CheckoutContext $context, InternalRequest $request)
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

    public function getPage(): CheckoutConfirmPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
