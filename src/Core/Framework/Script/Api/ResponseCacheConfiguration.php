<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

class ResponseCacheConfiguration
{
    private bool $enabled = true;

    private ?int $maxAge = null;

    private array $invalidationStates = [];

    private array $cacheTags = [];

    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function maxAge(int $maxAge): self
    {
        $this->maxAge = $maxAge;

        return $this;
    }

    public function invalidationState(string ...$invalidationStates): self
    {
        $this->invalidationStates = array_merge($this->invalidationStates, $invalidationStates);

        return $this;
    }

    public function tag(string ...$cacheTags): self
    {
        $this->cacheTags = array_merge($this->cacheTags, $cacheTags);

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
     */
    public function getInvalidationStates(): array
    {
        return $this->invalidationStates;
    }

    /**
     * @internal
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }
}
