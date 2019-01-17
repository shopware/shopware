<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\NavigationSidebar;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NavigationSidebarPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'navigation-sidebar.pagelet.loaded';

    /**
     * @var NavigationSidebarPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var NavigationSidebarPageletRequest
     */
    protected $request;

    public function __construct(
        NavigationSidebarPageletStruct $pagelet,
        CheckoutContext $context,
        NavigationSidebarPageletRequest $request
    ) {
        $this->pagelet = $pagelet;
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

    public function getPagelet(): NavigationSidebarPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): NavigationSidebarPageletRequest
    {
        return $this->request;
    }
}
