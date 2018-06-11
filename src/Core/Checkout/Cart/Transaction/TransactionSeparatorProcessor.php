<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Framework\Struct\StructCollection;

class TransactionSeparatorProcessor implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CheckoutContext $context
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
