<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ContentHomePageletLoadedEvent extends NestedEvent
{
    public const NAME = 'content-home.pagelet.loaded.event';

    /**
     * @var ContentHomePageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ContentHomePageletRequest
     */
    protected $request;

    public function __construct(
        ContentHomePageletStruct $pagelet,
        CheckoutContext $context,
        ContentHomePageletRequest $request
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

    public function getPagelet(): ContentHomePageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ContentHomePageletRequest
    {
        return $this->request;
    }
}
