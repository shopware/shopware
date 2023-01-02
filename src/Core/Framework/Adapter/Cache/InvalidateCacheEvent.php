<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
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
