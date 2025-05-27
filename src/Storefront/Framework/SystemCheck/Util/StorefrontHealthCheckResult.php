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
    public function __construct(
        protected string $storefrontUrl,
        protected int $responseCode,
        protected float $responseTime,
    ) {
    }

    public static function create(string $storefrontUrl, int $responseCode, float $responseTime): self
    {
        return new self($storefrontUrl, $responseCode, $responseTime);
    }

    public function getStorefrontUrl(): string
    {
        return $this->storefrontUrl;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function getResponseTime(): float
    {
        return $this->responseTime;
    }
}
