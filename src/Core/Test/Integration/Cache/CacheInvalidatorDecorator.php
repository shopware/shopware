<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Cache;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class CacheInvalidatorDecorator extends CacheInvalidator
{
    public function __construct(private readonly CacheInvalidator $decorated)
    {
    }

    public function invalidate(array $tags, bool $force = false): void
    {
        // force invalidate to clear cache directly in test env
        $this->decorated->invalidate($tags, true);
    }

    public function invalidateExpired(): array
    {
        return $this->decorated->invalidateExpired();
    }
}
