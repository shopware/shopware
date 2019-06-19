<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartDataCollectorInterface
{
    public function collect(StructCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void;
}
