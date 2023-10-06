<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TransactionProcessor
{
    public function process(Cart $cart, SalesChannelContext $context): TransactionCollection
    {
        $price = $cart->getPrice()->getTotalPrice();

        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    $price,
                    $price,
                    $cart->getPrice()->getCalculatedTaxes(),
                    $cart->getPrice()->getTaxRules()
                ),
                $context->getPaymentMethod()->getId()
            ),
        ]);
    }
}
