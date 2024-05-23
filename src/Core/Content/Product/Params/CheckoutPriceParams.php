<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Params;

use Shopware\Core\Framework\Struct\Extendable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CheckoutPriceParams
{
    use Extendable;

    /**
     * @param array<QuantityPriceId> $ids
     */
    public function __construct(public array $ids, public readonly SalesChannelContext $context) {}
}
