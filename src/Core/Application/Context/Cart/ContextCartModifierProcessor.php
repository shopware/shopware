<?php declare(strict_types=1);

namespace Shopware\Application\Context\Cart;

use Shopware\Application\Context\Exception\UnsupportedModifierTypeException;
use Shopware\Application\Context\Repository\ContextCartModifierRepository;
use Shopware\Application\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Application\Context\Struct\ContextCartModifierSearchResult;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Checkout\Rule\Specification\Scope\CalculatedLineItemScope;
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
