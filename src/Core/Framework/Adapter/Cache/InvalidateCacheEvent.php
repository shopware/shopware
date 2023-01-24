<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
class InvalidateCacheEvent extends Event
{
    public function __construct(protected array $keys)
    {
    }

    public function getKeys(): array
    {
        return $this->keys;
    }
}
