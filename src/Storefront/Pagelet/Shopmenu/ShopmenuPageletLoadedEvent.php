<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Shopmenu;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ShopmenuPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'shopmenu.pagelet.loaded.event';

    /**
     * @var ShopmenuPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ShopmenuPageletRequest
     */
    protected $request;

    public function __construct(
        ShopmenuPageletStruct $pagelet,
        CheckoutContext $context,
        ShopmenuPageletRequest $request
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

    public function getPagelet(): ShopmenuPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ShopmenuPageletRequest
    {
        return $this->request;
    }
}
