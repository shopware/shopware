<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Base class for a PriceProcessorInterface that computes its own default price modification(s),
 * e.g. from plugin configuration. Once a cart originates from an existing order (detected via
 * OrderConverter::ORIGINAL_ID), this becomes a permanent no-op: OrderPriceModificationCollector/
 * OrderPriceModificationProcessor take over as the sole source of truth so a subclass's defaults
 * never fight with admin-edited state. A subclass therefore only seeds a *new* order once, at
 * placement.
 */
#[Package('checkout')]
abstract class AbstractOrderAwarePriceProcessor implements PriceProcessorInterface
{
    final public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        PriceCollection $prices,
        PriceCollection $shippingCosts,
        PriceCollection $additionalCosts,
        float $taxExemptAdjustment,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): PriceModifierResult {
        if ($original->hasExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)) {
            return new PriceModifierResult($prices, $shippingCosts);
        }

        return $this->computeDefaults($data, $original, $toCalculate, $prices, $shippingCosts, $additionalCosts, $taxExemptAdjustment, $context, $behavior);
    }

    abstract protected function computeDefaults(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        PriceCollection $prices,
        PriceCollection $shippingCosts,
        PriceCollection $additionalCosts,
        float $taxExemptAdjustment,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): PriceModifierResult;
}
