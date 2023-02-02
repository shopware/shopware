<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Contracts\EventDispatcher\Event;

class InvalidateCacheEvent extends Event
{
    protected array $keys;

    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    public function getKeys(): array
    {
        return $this->keys;
    }
}
