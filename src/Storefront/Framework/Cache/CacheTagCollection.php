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
        if (\is_string($tags)) {
            $this->tags[$tags] = true;
        }

        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                $this->tags[$tag] = true;
            }
        }
    }

    public function getTags(): array
    {
        return array_keys($this->tags);
    }
}
