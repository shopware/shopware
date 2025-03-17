<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
class MockedCacheInvalidator extends CacheInvalidator
{
    /**
     * @var array<string>
     */
    private array $invalidatedTags = [];

    /**
     * @var array<string>
     */
    private array $forceInvalidatedTags = [];

    public function __construct()
    {
    }

    public function invalidate(array $tags, bool $force = false): void
    {
        if ($force) {
            $this->forceInvalidatedTags = array_merge($this->forceInvalidatedTags, $tags);

            return;
        }

        $this->invalidatedTags = array_merge($this->invalidatedTags, $tags);
    }

    /**
     * @return array<string>
     */
    public function getInvalidatedTags(): array
    {
        return $this->invalidatedTags;
    }

    /**
     * @return array<string>
     */
    public function getForceInvalidatedTags(): array
    {
        return $this->forceInvalidatedTags;
    }
}
