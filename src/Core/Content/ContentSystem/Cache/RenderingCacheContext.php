<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Cache;

use Shopware\Core\Framework\Log\Package;

/**
 * Passed through the pipeline and filled by data loaders.
 * Routes read the final state to determine caching behavior.
 */
#[Package('discovery')]
class RenderingCacheContext
{
    /**
     * @var list<string>
     */
    private array $tags = [];

    private bool $disabled = false;

    /**
     * @param list<string> $tags
     */
    public function addTags(array $tags): void
    {
        $this->tags = array_values(array_unique([...$this->tags, ...$tags]));
    }

    public function disable(): void
    {
        $this->disabled = true;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
