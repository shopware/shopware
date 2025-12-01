<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CacheKey
{
    public function __construct(
        public readonly string $key,
        public readonly bool $isCacheable,
    ) {
    }
}
