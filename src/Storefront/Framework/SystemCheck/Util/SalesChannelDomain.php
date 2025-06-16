<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelDomain extends Struct
{
    private function __construct(
        public readonly string $salesChannelId,
        public readonly string $url,
    ) {
    }

    public static function create(string $salesChannelId, string $url): self
    {
        return new self($salesChannelId, $url);
    }
}
