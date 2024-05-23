<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Params;

use Shopware\Core\Framework\Struct\Extendable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorePriceParams
{
    use Extendable;

    /**
     * @param array<int|string, string> $ids
     */
    public function __construct(
        public array $ids,
        public readonly SalesChannelContext $context
    ) {
    }
}
