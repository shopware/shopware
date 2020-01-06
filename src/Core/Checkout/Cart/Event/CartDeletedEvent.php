<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CartDeletedEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(SalesChannelContext $context)
    {
        $this->context = $context;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }
}
