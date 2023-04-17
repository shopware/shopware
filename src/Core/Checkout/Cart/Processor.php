<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Hook\CartHook;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class Processor
{
    /**
     * @internal
     *
     * @param iterable<CartProcessorInterface> $processors
     * @param iterable<CartDataCollectorInterface> $collectors
     */
    public function __construct(
        private readonly Validator $validator,
        private readonly AmountCalculator $amountCalculator,
        private readonly TransactionProcessor $transactionProcessor,
        private readonly iterable $processors,
        private readonly iterable $collectors,
        private readonly ScriptExecutor $executor
    ) {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        return Profiler::trace('cart::process', function () use ($original, $context, $behavior) {
            $cart = new Cart($original->getToken());
            $cart->setCustomerComment($original->getCustomerComment());
            $cart->setAffiliateCode($original->getAffiliateCode());
            $cart->setCampaignCode($original->getCampaignCode());
            $cart->setBehavior($behavior);
            $cart->addState(...$original->getStates());

            if ($behavior->hookAware()) {
                // reset modified state that apps always have the same entry state
                foreach ($original->getLineItems()->getFlat() as $item) {
                    $item->markUnModifiedByApp();
                }
            }

            // move data from previous calculation into new cart
            $cart->setData($original->getData());

            $this->runProcessors($original, $cart, $context, $behavior);

            if ($behavior->hookAware()) {
                $this->executor->execute(new CartHook($cart, $context));
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
        }, 'cart');
    }

    private function runProcessors(Cart $original, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($original->getLineItems()->count() <= 0) {
            $cart->addErrors(...array_values($original->getErrors()->getPersistent()->getElements()));

            $cart->setExtensions($original->getExtensions());

            return;
        }

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
