<?php

namespace Shopware\CartBridge\Modifier;

use Shopware\Api\Context\Repository\ContextCartModifierRepository;
use Shopware\Api\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\Price\AbsolutePriceCalculator;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ContextCartModifierProcessor implements CartProcessorInterface
{

    const TYPE = 'context_cart_modifier';

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

    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        StorefrontContext $context
    ): void
    {
        $ids = $context->getContextRulesIds();
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('context_cart_modifier.contextRuleId', $ids));
        $contextCartModifiers = $this->repository->search($criteria, $context->getShopContext())->getElements();
        
        /** @var ContextCartModifierBasicStruct $modifier */
        foreach ($contextCartModifiers as $modifier) {
            if($modifier->getRule()) {
                if(!$modifier->getRule()->match($calculatedCart, $context))
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

    private function calculate(
        ContextCartModifierBasicStruct $modifier,
        CalculatedCart $calculatedCart,
        StorefrontContext $context
    ): ?CalculatedPrice {

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();

        switch (true) {
            case $modifier->getAbsolute() !== null:
                return $this->absolutePriceCalculator->calculate(
                    $modifier->getAbsolute(),
                    new CalculatedPriceCollection(),
                    $context
                );
            case $modifier->getPercental() !== null:
                return $this->percentagePriceCalculator->calculate(
                    $modifier->getPercental(),
                    $goods->getPrices(),
                    $context
                );
        }
        return null;
    }
}