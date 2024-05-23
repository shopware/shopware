<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

class InvalidateProductCache implements ProductChangedEventInterface
{
    public function __construct(private readonly array $ids, public readonly bool $force = false)
    {
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
