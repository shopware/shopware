<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductNoLongerAvailableEvent extends Event implements ShopwareEvent, ProductChangedEventInterface
{
    public function __construct(
        protected array $ids,
        protected Context $context
    ) {
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
