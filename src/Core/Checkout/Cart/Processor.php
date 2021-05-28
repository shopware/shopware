<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Processor
{
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

    /**
     * @var CartProcessorInterface[]
     */
    private $processors;

    /**
     * @var CartDataCollectorInterface[]
     */
    private $collectors;

    public function __construct(
        Validator $validator,
        AmountCalculator $amountCalculator,
        TransactionProcessor $transactionProcessor,
        iterable $processors,
        iterable $collectors
    ) {
        $this->validator = $validator;
        $this->amountCalculator = $amountCalculator;
        $this->transactionProcessor = $transactionProcessor;
        $this->processors = $processors;
        $this->collectors = $collectors;
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        $cart = new Cart($original->getName(), $original->getToken());
        $cart->setCustomerComment($original->getCustomerComment());
        $cart->setAffiliateCode($original->getAffiliateCode());
        $cart->setCampaignCode($original->getCampaignCode());

        // move data from previous calculation into new cart
        $cart->setData($original->getData());

        // enrich cart with all required data
        foreach ($this->collectors as $collector) {
            $collector->collect($cart->getData(), $original, $context, $behavior);
        }

        $cart->addErrors(...array_values($original->getErrors()->getPersistent()->getElements()));

        $cart->setExtensions($original->getExtensions());

        $this->calculateAmount($context, $cart);

        // start processing, cart will be filled step by step with line items of original cart
        foreach ($this->processors as $processor) {
            $processor->process($cart->getData(), $original, $cart, $context, $behavior);

            $this->calculateAmount($context, $cart);
        }

        $this->calculateAmount($context, $cart);

        $cart->addErrors(
            ...$this->validator->validate($cart, $context)
        );

        $cart->setTransactions(
            $this->transactionProcessor->process($cart, $context)
        );

        $cart->setRuleIds($context->getRuleIds());

        return $cart;
    }

    private function calculateAmount(SalesChannelContext $context, Cart $cart): void
    {
        $amount = $this->amountCalculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );

        $cart->setPrice($amount);
    }
}
