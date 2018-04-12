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
use Shopware\CartBridge\Exception\UnsupportedModifierType;
use Shopware\Context\Exception\UnsupportedOperatorException;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ContextCartModifierProcessor implements CartProcessorInterface
{
    const TYPE = 'context_cart_modifier';

    const ABSOLUTE_MODIFIER = 'absolute';

    const PERCENTAL_MODIFIER = 'percental';

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
     * @throws UnsupportedModifierType
     */
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        StorefrontContext $context
    ): void {
        /** @var ContextCartModifierSearchResult $contextCartModifiers */
        $contextCartModifiers = $dataCollection->get(ContextCartModifierCollector::CONTEXT_CART_MODIFIER);

        if (!$contextCartModifiers) {
            return;
        }

        /** @var ContextCartModifierBasicStruct $modifier */
        foreach ($contextCartModifiers->getElements() as $modifier) {
            if (!in_array($modifier->getContextRuleId(), $context->getContextRulesIds())) {
                continue;
            }

            $price = $this->calculate($modifier, $calculatedCart, $context);
            if ($price) {
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
    }

    /**
     * @throws UnsupportedModifierType
     */
    private function calculate(
        ContextCartModifierBasicStruct $modifier,
        CalculatedCart $calculatedCart,
        StorefrontContext $context
    ): ?CalculatedPrice {
//        $prices = new CalculatedPriceCollection();
//        if ($modifier->getRule()->)
        // TODO use to restrict the discount
//        if($modifier->getRule()) {
//            if(!$modifier->getRule()->match($calculatedCart, $context))
//                continue;
//        }

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();

        switch ($modifier->getType()) {
            case self::ABSOLUTE_MODIFIER:
                return $this->absolutePriceCalculator->calculate(
                    $modifier->getAmount(),
                    $goods->getPrices(),
                    $context
                );
            case self::PERCENTAL_MODIFIER:
                return $this->percentagePriceCalculator->calculate(
                    $modifier->getAmount(),
                    $goods->getPrices(),
                    $context
                );
            default:
                throw new UnsupportedModifierType($modifier->getType(), self::class);
        }
    }
}
