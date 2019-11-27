<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryCalculator
{
    public const CALCULATION_BY_LINE_ITEM_COUNT = 1;

    public const CALCULATION_BY_PRICE = 2;

    public const CALCULATION_BY_WEIGHT = 3;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(CartDataCollection $data, Cart $cart, DeliveryCollection $deliveries, SalesChannelContext $context): void
    {
        foreach ($deliveries as $delivery) {
            $this->calculateDelivery($data, $cart, $delivery, $context);
        }
    }

    private function calculateDelivery(CartDataCollection $data, Cart $cart, Delivery $delivery, SalesChannelContext $context): void
    {
        $costs = null;
        if ($delivery->getShippingCosts()->getUnitPrice() > 0) {
            $costs = $this->calculateShippingCosts(
                $delivery->getShippingCosts()->getTotalPrice(),
                $delivery->getPositions()->getLineItems(),
                $context
            );

            $delivery->setShippingCosts($costs);

            return;
        }

        if ($this->hasDeliveryShippingFreeItems($delivery)) {
            $costs = $this->calculateShippingCosts(
                0,
                $delivery->getPositions()->getLineItems(),
                $context
            );

            $delivery->setShippingCosts($costs);

            return;
        }

        $key = DeliveryProcessor::buildKey($delivery->getShippingMethod()->getId());

        if (!$data->has($key)) {
            throw new ShippingMethodNotFoundException($delivery->getShippingMethod()->getId());
        }

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $data->get($key);

        $prices = $shippingMethod->getPrices();

        $prices->sort(
            function (ShippingMethodPriceEntity $priceEntityA, ShippingMethodPriceEntity $priceEntityB) {
                return $priceEntityA->getPrice() <=> $priceEntityB->getPrice();
            }
        );

        foreach ($prices as $price) {
            if ($price->getRuleId() !== null && !in_array($price->getRuleId(), $context->getRuleIds(), true)) {
                continue;
            }

            if (!$this->matches($delivery, $price, $context)) {
                continue;
            }

            $costs = $this->calculateShippingCosts(
                $price->getPrice(),
                $delivery->getPositions()->getLineItems(),
                $context
            );

            break;
        }

        if (!$costs) {
            $cart->addErrors(
                new ShippingMethodBlockedError((string) $shippingMethod->getTranslation('name'))
            );

            return;
        }

        $delivery->setShippingCosts($costs);
    }

    private function hasDeliveryShippingFreeItems(Delivery $delivery): bool
    {
        foreach ($delivery->getPositions()->getLineItems()->getIterator() as $lineItem) {
            if ($lineItem->getDeliveryInformation() && $lineItem->getDeliveryInformation()->getFreeDelivery()) {
                return true;
            }
        }

        return false;
    }

    private function matches(Delivery $delivery, ShippingMethodPriceEntity $shippingMethodPrice, SalesChannelContext $context): bool
    {
        if ($shippingMethodPrice->getCalculationRuleId()) {
            return in_array($shippingMethodPrice->getCalculationRuleId(), $context->getRuleIds(), true);
        }

        $start = $shippingMethodPrice->getQuantityStart();
        $end = $shippingMethodPrice->getQuantityEnd();

        switch ($shippingMethodPrice->getCalculation()) {
            case self::CALCULATION_BY_PRICE:
                $value = $delivery->getPositions()->getPrices()->sum()->getTotalPrice();

                break;
            case self::CALCULATION_BY_LINE_ITEM_COUNT:
                $value = $delivery->getPositions()->getQuantity();

                break;
            case self::CALCULATION_BY_WEIGHT:
                $value = $delivery->getPositions()->getWeight();

                break;
            default:
                $value = $delivery->getPositions()->getLineItems()->getPrices()->sum()->getTotalPrice() / 100;

                break;
        }

        // $end (optional) exclusive
        return ($value >= $start) && (!$end || $value < $end);
    }

    private function calculateShippingCosts(float $price, LineItemCollection $calculatedLineItems, SalesChannelContext $context): CalculatedPrice
    {
        $rules = $this->percentageTaxRuleBuilder->buildRules(
            $calculatedLineItems->getPrices()->sum()
        );

        $definition = new QuantityPriceDefinition($price, $rules, $context->getContext()->getCurrencyPrecision(), 1, true);

        return $this->priceCalculator->calculate($definition, $context);
    }
}
