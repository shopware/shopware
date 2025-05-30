<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontHealthCheckResult extends Struct
{
    private function __construct(
        public readonly string $storefrontUrl,
        public readonly int $responseCode,
        public readonly float $responseTime,
    ) {
    }

    public static function create(string $storefrontUrl, int $responseCode, float $responseTime): self
    {
        return new self($storefrontUrl, $responseCode, $responseTime);
    }
}
