<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageletRequestEvent extends NestedEvent
{
    public const NAME = 'navigation.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var NavigationPageletRequest
     */
    protected $navigationPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, NavigationPageletRequest $navigationPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->navigationPageletRequest = $navigationPageletRequest;
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

    public function getNavigationPageletRequest(): NavigationPageletRequest
    {
        return $this->navigationPageletRequest;
    }
}
