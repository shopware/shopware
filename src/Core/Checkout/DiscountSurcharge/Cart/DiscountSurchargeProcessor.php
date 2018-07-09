<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;

use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Rule\CalculatedLineItemScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeStruct;
use Shopware\Core\Checkout\DiscountSurcharge\Exception\UnsupportedModifierTypeException;
use Shopware\Core\Framework\Struct\StructCollection;

class DiscountSurchargeProcessor implements CartProcessorInterface
{
    public const TYPE = 'discount_surcharge';

    public const ABSOLUTE_MODIFIER = 'absolute';

    public const PERCENTAL_MODIFIER = 'percental';

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    public function __construct(
        AbsolutePriceCalculator $absolutePriceCalculator,
        PercentagePriceCalculator $percentagePriceCalculator
    ) {
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
    }

    /**
     * @throws UnsupportedModifierTypeException
     */
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CheckoutContext $context
    ): void {
        /** @var DiscountSurchargeSearchResult $discountSurcharges */
        $discountSurcharges = $dataCollection->get(self::TYPE);

        if (!$discountSurcharges) {
            return;
        }

        /** @var DiscountSurchargeStruct $modifier */
        foreach ($discountSurcharges->getElements() as $modifier) {
            if (!in_array($modifier->getRuleId(), $context->getRuleIds(), true)) {
                continue;
            }

            $price = $this->calculate($modifier, $calculatedCart, $context);
            if (!$price || $price->getTotalPrice() == 0) {
                continue;
            }

            $calculatedLineItem = new CalculatedLineItem(
                $modifier->getId(),
                $price,
                1,
                self::TYPE,
                $modifier->getName()
            );

            $calculatedCart->getCalculatedLineItems()->add($calculatedLineItem);
        }
    }

    /**
     * @throws UnsupportedModifierTypeException
     */
    private function calculate(
        DiscountSurchargeStruct $modifier,
        CalculatedCart $calculatedCart,
        CheckoutContext $context
    ): Price {
        $prices = new PriceCollection();
        foreach ($calculatedCart->getCalculatedLineItems() as $calculatedLineItem) {
            $match = $modifier->getFilterRule()->match(
                new CalculatedLineItemScope($calculatedLineItem, $context)
            );

            if (!$match->matches()) {
                continue;
            }
            $prices->add($calculatedLineItem->getPrice());
        }

        switch ($modifier->getType()) {
            case self::ABSOLUTE_MODIFIER:
                return $this->absolutePriceCalculator->calculate(
                    $modifier->getAmount(),
                    $prices,
                    $context
                );
            case self::PERCENTAL_MODIFIER:
                return $this->percentagePriceCalculator->calculate(
                    $modifier->getAmount(),
                    $prices,
                    $context
                );
            default:
                throw new UnsupportedModifierTypeException($modifier->getType(), self::class);
        }
    }
}
