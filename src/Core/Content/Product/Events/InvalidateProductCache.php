<?php

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\Event\ForceInvalidate;

class InvalidateProductCache implements ProductChangedEventInterface, ForceInvalidate
{
    public function __construct(private readonly array $ids)
    {
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
