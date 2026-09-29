<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Hook\CartHook;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopware\Core\Checkout\PriceModifier\PriceCollectorInterface;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCalculatedPrice;
use Shopware\Core\Checkout\PriceModifier\PriceModifierCollection;
use Shopware\Core\Checkout\PriceModifier\PriceProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Util\FloatComparator;
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
     * @param iterable<PriceCollectorInterface> $priceCollectors
     * @param iterable<PriceProcessorInterface> $priceProcessors
     */
    public function __construct(
        private readonly Validator $validator,
        private readonly AmountCalculator $amountCalculator,
        private readonly CashRounding $rounding,
        private readonly TransactionProcessor $transactionProcessor,
        private readonly iterable $processors,
        private readonly iterable $collectors,
        private readonly ScriptExecutor $executor,
        private readonly iterable $priceCollectors = [],
        private readonly iterable $priceProcessors = []
    ) {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        return Profiler::trace('cart::process', function () use ($original, $context, $behavior) {
            $cart = new Cart($original->getToken());
            $cart->setCustomerComment($original->getCustomerComment());
            $cart->setAffiliateCode($original->getAffiliateCode());
            $cart->setCampaignCode($original->getCampaignCode());
            $cart->setSource($original->getSource());
            $cart->setErrorHash($original->getErrorHash());
            $cart->setPersisted($original->isPersisted());
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
            $this->applyPriceModifiers($original, $cart, $context, $behavior);

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

        foreach ($this->priceCollectors as $collector) {
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

    private function applyPriceModifiers(Cart $original, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $prices = $cart->getLineItems()->getPrices();
        $shippingCosts = $cart->getDeliveries()->getShippingCosts();
        $additionalCosts = new PriceCollection();
        $taxExemptAdjustment = 0.0;
        $modifiers = new PriceModifierCollection();

        $unmodifiedPrices = $prices;
        $unmodifiedShippingCosts = $shippingCosts;

        foreach ($this->priceProcessors as $processor) {
            $result = $processor->process($cart->getData(), $original, $cart, $prices, $shippingCosts, $additionalCosts, $taxExemptAdjustment, $context, $behavior);
            $prices = $result->prices;
            $shippingCosts = $result->shippingCosts;
            $additionalCosts = $additionalCosts->merge($result->additionalCosts);
            $taxExemptAdjustment += $result->taxExemptAdjustment;

            foreach ($result->modifiers as $modifier) {
                $modifiers->add($modifier);
            }
        }

        $cart->setPriceModifiers($modifiers);

        // Skip re-running AmountCalculator when the aggregated result shows nothing was actually
        // contributed this pass (no modifiers, no additional costs, no tax-exempt adjustment, and
        // both prices/shippingCosts came back unchanged) — the common case for a cart with no
        // persisted order_price_modification rows. This checks the actual result, not an assumed
        // per-processor contract: PriceProcessorInterface itself doesn't require an implementation to
        // no-op when there's nothing to apply -- only specific implementations happen to (see
        // OrderPriceModificationProcessor's and AbstractOrderAwarePriceProcessor's own docblocks).
        // Cart::price already holds the correct result from the calculateAmount() call above.
        if ($modifiers->count() === 0
            && $additionalCosts->count() === 0
            && $taxExemptAdjustment === 0.0
            && $prices === $unmodifiedPrices
            && $shippingCosts === $unmodifiedShippingCosts
        ) {
            return;
        }

        $price = $this->amountCalculator->calculate($prices, $shippingCosts, $context, $additionalCosts);

        if ($taxExemptAdjustment !== 0.0) {
            // A negative adjustment (reduction) is capped so totalPrice can't go below zero; a
            // positive one (surcharge) is applied in full, uncapped.
            $adjustment = $taxExemptAdjustment < 0.0
                ? max($taxExemptAdjustment, -$price->getTotalPrice())
                : $taxExemptAdjustment;

            // This is the last rounding step the grand total gets before Cart::price is set -- round
            // it the same way AmountCalculator rounds every other total (cashRound(), total-level
            // precision/interval), since a tax-exempt adjustment never passes through
            // AmountCalculator itself. (CartPrice's own constructor still applies its usual
            // FloatComparator::cast() noise cleanup afterwards, same as every other CartPrice value.)
            $adjustment = $this->rounding->cashRound($adjustment, $context->getTotalRounding());

            // Processors cap their own reductions against the running state they are passed, but
            // cannot foresee a later processor's contribution (or may ignore it) -- when this cap
            // still cuts the aggregate, the modifiers have to shrink with it, otherwise they would
            // add up to more than was actually deducted.
            $excess = $adjustment - $this->rounding->cashRound($taxExemptAdjustment, $context->getTotalRounding());
            if (FloatComparator::greaterThan($excess, 0.0)) {
                $cart->setPriceModifiers($this->trimTaxExemptReductions($modifiers, $excess, $context));
            }

            // Deliberately leaves getNetPrice()/getCalculatedTaxes() untouched: a tax-exempt
            // adjustment (e.g. a prepaid, tax-neutral multi-purpose voucher being redeemed, or a
            // tax-neutral fee already covered by a separately-taxed instrument) represents a payment
            // already received or due for part of the order, not a change in the value of what was
            // supplied — net and tax must keep describing the full, unadjusted goods/services value.
            // Only the amount still due (totalPrice/rawTotal) moves. This deliberately makes
            // totalPrice != netPrice + calculatedTaxes.getAmount() for this CartPrice, which nothing
            // in core enforces or assumes (no CartValidatorInterface checks it, and
            // TransactionProcessor charges getTotalPrice() directly, so the customer is correctly
            // charged the adjusted amount) — but it does mean any UI/document showing net, tax, and
            // total together should also show the price modifier that caused the gap, or it will
            // look like a calculation error. See Framework/Resources/views/documents/includes/
            // summary.html.twig's price-modifiers block, and the storefront/admin net-amount hints,
            // both added by this same patch.
            $price = new CartPrice(
                $price->getNetPrice(),
                $price->getTotalPrice() + $adjustment,
                $price->getPositionPrice(),
                $price->getCalculatedTaxes(),
                $price->getTaxRules(),
                $price->getTaxStatus(),
                $price->getRawTotal() + $adjustment
            );
        }

        $cart->setPrice($price);
    }

    /**
     * Shrinks the tax-exempt reduction modifiers by $excess in total, the last one first -- the
     * one that got applied against a remaining total that was no longer there. A fully consumed
     * modifier stays listed at zero, the reduction it stands for is still active.
     */
    private function trimTaxExemptReductions(PriceModifierCollection $modifiers, float $excess, SalesChannelContext $context): PriceModifierCollection
    {
        $elements = array_values($modifiers->getElements());

        for ($i = \count($elements) - 1; $i >= 0 && FloatComparator::greaterThan($excess, 0.0); --$i) {
            $modifier = $elements[$i];
            $price = $modifier->getPrice();

            if (!$modifier->getPriceDefinition()->isTaxExempt() || $price->getTotalPrice() >= 0.0) {
                continue;
            }

            $trim = min($excess, -$price->getTotalPrice());
            $excess -= $trim;

            $elements[$i] = $modifier->withPrice(new PriceModifierCalculatedPrice(
                $this->rounding->mathRound($price->getTotalPrice() + $trim, $context->getItemRounding()),
                $price->getCalculatedTaxes(),
                $price->getTaxRules()
            ));
        }

        return new PriceModifierCollection($elements);
    }
}
