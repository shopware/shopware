<?php declare(strict_types=1);

namespace Shopware\CartBridge\Modifier;

use Shopware\Api\Context\Repository\ContextCartModifierRepository;
use Shopware\Api\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Api\Context\Struct\ContextCartModifierSearchResult;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\Price\AbsolutePriceCalculator;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\CartBridge\Exception\UnsupportedModifierTypeException;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ContextCartModifierProcessor implements CartProcessorInterface
{
    public const TYPE = 'context_cart_modifier';

    public const ABSOLUTE_MODIFIER = 'absolute';

    public const PERCENTAL_MODIFIER = 'percental';

    /**
     * @var ContextCartModifierRepository
     */
    private $repository;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    public function __construct(
        ContextCartModifierRepository $contextCartModifierRepository,
        AbsolutePriceCalculator $absolutePriceCalculator,
        PercentagePriceCalculator $percentagePriceCalculator
    ) {
        $this->repository = $contextCartModifierRepository;
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
        StorefrontContext $context
    ): void {
        /** @var ContextCartModifierSearchResult $contextCartModifiers */
        $contextCartModifiers = $dataCollection->get(self::TYPE);

        if (!$contextCartModifiers) {
            return;
        }

        /** @var ContextCartModifierBasicStruct $modifier */
        foreach ($contextCartModifiers->getElements() as $modifier) {
            if (!in_array($modifier->getContextRuleId(), $context->getContextRuleIds(), true)) {
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
        ContextCartModifierBasicStruct $modifier,
        CalculatedCart $calculatedCart,
        StorefrontContext $context
    ): CalculatedPrice {
        $prices = new CalculatedPriceCollection();
        foreach ($calculatedCart->getCalculatedLineItems() as $calculatedLineItem) {
            $match = $modifier->getRule()->match(
                new CalculatedLineItemMatchContext($calculatedLineItem, $context)
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
