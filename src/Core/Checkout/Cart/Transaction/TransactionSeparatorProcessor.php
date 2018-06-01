<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\Transaction;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Framework\Struct\StructCollection;

class TransactionSeparatorProcessor implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CustomerContext $context
    ): void {
        $price = $calculatedCart->getPrice()->getTotalPrice();

        $calculatedCart->addTransaction(new Transaction(
            new CalculatedPrice(
                $price,
                $price,
                $calculatedCart->getPrice()->getCalculatedTaxes(),
                $calculatedCart->getPrice()->getTaxRules()
            ),
            $context->getPaymentMethod()->getId())
        );
    }
}
