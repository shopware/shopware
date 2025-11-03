<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Value object extended for cache attribute in request
 *
 * @phpstan-type CacheAttributeArray array{maxAge?: int, states?: list<string> }
 * @phpstan-type CacheAttributeType CacheAttributeArray|true|CacheAttribute
 *
 * @internal
 */
#[Package('framework')]
readonly class CacheAttribute
{
    public function __construct(
        public ?int $maxAge = null,
        public ?int $sMaxAge = null,
        public ?string $policyModifier = null,
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         *
         * @var list<string>|null
         */
        public ?array $states = null,
    ) {
    }
}
