<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CartDeletedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var SalesChannelContext
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $context;

    public function __construct(SalesChannelContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
