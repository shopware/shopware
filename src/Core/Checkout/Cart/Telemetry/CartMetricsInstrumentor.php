<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Telemetry;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;

/**
 * Telemetry collaborator for {@see CartCalculator}: times a cart
 * calculation and derives the cart calculation metrics (duration, line item count, errors) from the
 * resulting cart, keeping telemetry logic and dependencies out of the Calculator.
 *
 * Depends on `Meter` and does manually calculation by design: the duration metric's `has_promotions`
 * label is only known after the calculation, so `Telemetry::instrument()` (labels fixed up-front) can't be used.
 *
 * Merely-hot path: relies on `Meter::emit`'s inner optimizations, not compiler pass based disabling.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('checkout')]
class CartMetricsInstrumentor
{
    public function __construct(
        private readonly Meter $meter,
        private readonly SalesChannelTypeResolver $salesChannelTypeResolver,
    ) {
    }

    /**
     * @param \Closure(): Cart $callback
     */
    public function measure(SalesChannelContext $context, \Closure $callback): Cart
    {
        $salesChannelType = $this->salesChannelTypeResolver->resolve($context->getSalesChannel()->getTypeId());

        // Manual timing rather than Telemetry::instrument(): the duration metric carries a `has_promotions` label
        // that is only known once the cart has been (re)calculated.
        // The `cart-calculation` profiler span also lives here.
        $timer = ElapsedTimer::start();
        $cart = Profiler::trace('cart-calculation', $callback);
        $durationMs = $timer->getElapsedMs();

        $hasPromotions = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->count() > 0 ? 'yes' : 'no';

        $this->meter->emit(new ConfiguredMetric(
            name: 'cart.calculation.duration',
            value: $durationMs,
            labels: ['sales_channel_type' => $salesChannelType, 'has_promotions' => $hasPromotions],
        ));
        // Counts top-level cart rows, not the flattened tree: a bundle of three sub-items is 1,
        // not 4. Nested children are excluded intentionally so basket size reflects rows rather than
        // structure — revisit if counting bundle sub-items proves the more useful signal.
        $this->meter->emit(new ConfiguredMetric(
            name: 'cart.line_items.count',
            value: $cart->getLineItems()->count(),
            labels: ['sales_channel_type' => $salesChannelType],
        ));

        $this->emitErrorCount($cart);

        return $cart;
    }

    private function emitErrorCount(Cart $cart): void
    {
        // A single aggregate count per calculation (no per-domain/level breakdown):
        // spikes flag checkout friction, and the flat counter keeps that signal cheap
        // and low-noise. Per-error emission and error classification probably is a logging concern.
        //
        // Count warning-level and above only — informational cart messages ("discount applied",
        // "method switched") sit at NOTICE and are excluded. Equals-or-bigger guards against new low-severity
        // levels added later
        $count = 0;
        foreach ($cart->getErrors() as $error) {
            if ($error->getLevel() >= Error::LEVEL_WARNING) {
                ++$count;
            }
        }

        if ($count === 0) {
            return;
        }

        $this->meter->emit(new ConfiguredMetric(name: 'cart.errors.count', value: $count));
    }
}
