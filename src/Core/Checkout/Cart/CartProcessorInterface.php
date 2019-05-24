<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartProcessorInterface
{
    public function process(
        StructCollection $data,
        Cart $original,
        Cart $calculated,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void;
}
