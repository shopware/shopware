<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractTaxProvider
{
    abstract public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult;
}
