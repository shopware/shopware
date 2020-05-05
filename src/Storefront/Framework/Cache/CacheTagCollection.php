<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

class CacheTagCollection
{
    /**
     * @var array
     */
    private $tags = [];

    public function reset(): void
    {
        $this->tags = [];
    }

    /**
     * @param string|array $tags
     */
    public function add($tags): void
    {
        if (is_string($tags)) {
            $this->tags[] = $tags;
        }

        if (is_array($tags)) {
            $this->tags = array_unique(array_merge($this->tags, array_values($tags)));
        }
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
