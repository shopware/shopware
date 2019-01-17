<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;

class ContentHeaderPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'content-header.pagelet.loaded.event';

    /**
     * @var ContentHeaderPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(ContentHeaderPageletStruct $page, CheckoutContext $context, InternalRequest $request)
    {
        $this->pagelet = $page;
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

    public function getPagelet(): ContentHeaderPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
