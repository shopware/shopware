<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Shopmenu;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class ShopmenuPageletRequestEvent extends NestedEvent
{
    public const NAME = 'shopmenu.pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var ShopmenuPageletRequest
     */
    private $shopmenuPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, ShopmenuPageletRequest $shopmenuPageRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->shopmenuPageletRequest = $shopmenuPageRequest;
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

    public function getShopmenuPageletRequest(): ShopmenuPageletRequest
    {
        return $this->shopmenuPageletRequest;
    }
}
