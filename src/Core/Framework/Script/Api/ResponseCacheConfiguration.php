<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ResponseCacheConfiguration
{
    private bool $enabled = true;

    private ?int $maxAge = null;

    /**
     * @var list<string>
     */
    private array $invalidationStates = [];

    /**
     * @var list<string>
     */
    private array $cacheTags = [];

    /**
     * Calling `disable()` during script execution, will prevent that the result will be cached.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * `maxAge()` allows you to define how long the result should be cached.
     *
     * @param int $maxAge The maximum number of seconds this response should be cached.
     */
    public function maxAge(int $maxAge): self
    {
        $this->maxAge = $maxAge;

        return $this;
    }

    /**
     * `invalidationState()` allows you to define states when the cache should be ignored.
     *
     * @param string ...$invalidationStates The states when the cache should be ignored, e.g. "logged-in" or "cart-filled".
     */
    public function invalidationState(string ...$invalidationStates): self
    {
        $this->invalidationStates = array_values(array_merge($this->invalidationStates, $invalidationStates));

        return $this;
    }

    /**
     * `tag()` allows you to tag the cached response, so you can later invalidate it through a `cache-invalidation` script.
     *
     * @param string ...$cacheTags The tags of the cache item.
     */
    public function tag(string ...$cacheTags): self
    {
        $this->cacheTags = array_values(array_merge($this->cacheTags, $cacheTags));

        return $this;
    }

    /**
     * @internal
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @internal
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function getInvalidationStates(): array
    {
        return $this->invalidationStates;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }
}
