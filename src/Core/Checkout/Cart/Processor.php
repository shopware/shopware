<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Processor
{
    /**
     * @var Calculator
     */
    private $calculator;

    /**
     * @var DeliveryProcessor
     */
    private $deliveryProcessor;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var AmountCalculator
     */
    private $amountCalculator;

    /**
     * @var TransactionProcessor
     */
    private $transactionProcessor;

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

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
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
