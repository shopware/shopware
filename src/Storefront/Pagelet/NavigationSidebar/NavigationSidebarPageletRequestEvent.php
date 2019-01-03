<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class NavigationSidebarPageletRequestEvent extends NestedEvent
{
    public const NAME = 'navigationsidebar.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var NavigationSidebarPageletRequest
     */
    protected $navigationSidebarPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, NavigationSidebarPageletRequest $navigationSidebarPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->navigationSidebarPageletRequest = $navigationSidebarPageletRequest;
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

    public function getNavigationSidebarPageletRequest(): NavigationSidebarPageletRequest
    {
        return $this->navigationSidebarPageletRequest;
    }
}
