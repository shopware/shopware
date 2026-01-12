<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductTypeChangedEvent extends Event implements ShopwareEvent
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private readonly array $ids,
        private readonly string $type,
        private readonly Context $context
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
