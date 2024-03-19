<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Event;

class AddCacheTagEvent
{
    public array $tags;

    public function __construct(string ...$tags)
    {
        $this->tags = $tags;
    }

    public function add(string ...$tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }
}
