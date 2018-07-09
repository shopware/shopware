<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
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

    public function __construct(Calculator $calculator, DeliveryProcessor $deliveryProcessor, Validator $validator)
    {
        $this->calculator = $calculator;
        $this->deliveryProcessor = $deliveryProcessor;
        $this->validator = $validator;
    }

    public function process(Cart $original, CheckoutContext $context): Cart
    {
        $cart = new Cart($original->getName(), $original->getToken());

        //calculate all line items and add new calculated line items to new cart
        $lineItems = $this->calculator->calculate($original, $context);
        $cart->addLineItems($lineItems);

        //add line items to deliveries and calculate deliveries
        $deliveries = $this->deliveryProcessor->process($cart, $context);
        $cart->addDeliveries($deliveries);

        $errors = $this->validator->validate($cart, $context);
        $cart->addErrors($errors);

        return $cart;
    }
}
