<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\CheckoutContext;

class TransactionProcessor
{
    public function process(Cart $cart, CheckoutContext $context): TransactionCollection
    {
        $price = $cart->getPrice()->getTotalPrice();

        return new TransactionCollection([
            new Transaction(
                new Price(
                    $price,
                    $price,
                    $cart->getPrice()->getCalculatedTaxes(),
                    $cart->getPrice()->getTaxRules()
                ),
                $context->getPaymentMethod()->getId()
            )
        ]);
    }
}