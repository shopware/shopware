<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ProductNoLongerAvailableEvent extends Event implements ShopwareEvent, ProductChangedEventInterface
{
    protected array $ids;

    protected Context $context;

    public function __construct(array $ids, Context $context)
    {
        $this->ids = $ids;
        $this->context = $context;
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
