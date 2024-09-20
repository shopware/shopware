<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class InvalidateCacheEvent extends Event
{
    /**
     * @param array<string> $keys
     */
    public function __construct(protected array $keys)
    {
    }

    /**
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
