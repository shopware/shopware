<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\CheckoutContext;

class Processor
{
    /**
     * @var Calculator
     */
    protected $calculator;

    /**
     * @var DeliveryProcessor
     */
    protected $deliveryProcessor;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @var AmountCalculator
     */
    protected $amountCalculator;

    /**
     * @var TransactionProcessor
     */
    protected $transactionProcessor;

    public function __construct(
        Calculator $calculator,
        DeliveryProcessor $deliveryProcessor,
        Validator $validator,
        AmountCalculator $amountCalculator,
        TransactionProcessor $transactionProcessor
    ) {
        $this->calculator = $calculator;
        $this->deliveryProcessor = $deliveryProcessor;
        $this->validator = $validator;
        $this->amountCalculator = $amountCalculator;
        $this->transactionProcessor = $transactionProcessor;
    }

    public function process(Cart $original, CheckoutContext $context, CartBehavior $behavior): Cart
    {
        $cart = new Cart($original->getName(), $original->getToken());

        //calculate all line items and add new calculated line items to new cart
        $cart->setLineItems(
            $this->calculator->calculate($original, $context, $behavior)
        );

        //add line items to deliveries and calculate deliveries
        $cart->setDeliveries(
            $this->deliveryProcessor->process(
                $original,
                $cart->getLineItems(),
                $context,
                $behavior
            )
        );

        $cart->setPrice(
            $this->amountCalculator->calculate(
                $cart->getLineItems()->getPrices(),
                $cart->getDeliveries()->getShippingCosts(),
                $context
            )
        );

        $cart->addErrors(
            $this->validator->validate($cart, $context)
        );

        $cart->setTransactions(
            $this->transactionProcessor->process($cart, $context)
        );

        $cart->setExtensions($original->getExtensions());

        return $cart;
    }
}
