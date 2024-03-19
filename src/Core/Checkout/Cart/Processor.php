<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Hook\CartHook;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * You should use `CartCalculator` to calculate the cart when you just want to calculate it
 * The processor has a behavior parameter which is used to control the behavior of the cart calculation for different scenarios like recalculation.
 */
#[Package('checkout')]
class Processor
{
    private ?array $rules = null;

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
        private readonly ScriptExecutor $executor,
        private readonly Connection $connection,
    ) {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        return Profiler::trace('cart::process', function () use ($original, $context, $behavior) {
            $cart = $this->init($original, $behavior);

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

            if (!Feature::isActive('cache_rework')) {
                $cart->setRuleIds($context->getRuleIds());
            }

            return $cart;
        }, 'cart');
    }

    private function matchRules(Cart $cart, SalesChannelContext $context): void
    {
        if (!Feature::isActive('cache_rework')) {
            return;
        }
        $rules = $this->loadRules();

        $matches = [];

        $scope = new CartRuleScope(cart: $cart, context: $context);

        foreach ($rules as $id => $rule) {
            if ($rule->match($scope)) {
                $matches[] = $id;
            }
        }

        $cart->setRuleIds($matches);
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

            $this->matchRules($cart, $context);
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

    private function init(Cart $original, CartBehavior $behavior): Cart
    {
        $cart = new Cart($original->getToken());
        $cart->setCustomerComment($original->getCustomerComment());
        $cart->setAffiliateCode($original->getAffiliateCode());
        $cart->setCampaignCode($original->getCampaignCode());
        $cart->setSource($original->getSource());
        $cart->setBehavior($behavior);
        $cart->addState(...$original->getStates());

        // move data from previous calculation into new cart
        $cart->setData($original->getData());

        if ($behavior->hookAware()) {
            // reset modified state that apps always have the same entry state
            foreach ($original->getLineItems()->getFlat() as $item) {
                $item->markUnModifiedByApp();
            }
        }

        return $cart;
    }

    /**
     * @return array<Rule>
     */
    private function loadRules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $payloads = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), payload FROM rule WHERE invalid = 0 ORDER BY priority DESC');

        $rules = [];
        foreach ($payloads as $id => $payload) {
            try {
                $rules[$id] = unserialize($payload);
            } catch (\Throwable $e) {
                // todo@skroblin log rule exception
            }
        }

        return $this->rules = $rules;
    }
}
