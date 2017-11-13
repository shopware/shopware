<?php

namespace Shopware\CartBridge\Order;


use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\LineItem\Discount;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class MinimumOrderAmountProcessor implements CartProcessorInterface
{

    /** @var  PriceCalculator */
    private $priceCalculator;

    /** @var  PercentageTaxRuleBuilder */
    private $percentageTaxRuleBuilder;

    /**
     * MinimumOrderAmountProcessor constructor.
     */
    public function __construct(PriceCalculator $priceCalculator, PercentageTaxRuleBuilder $percentageTaxRuleBuilder)
    {
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function process(
        CartContainer $cartContainer,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void
    {
        if (!$context->getCustomer()) {
            return;
        }

        $customerGroup = $context->getCurrentCustomerGroup();
        if (!$customerGroup->getMinimumOrderAmount()) {
            return;
        }

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();
        if ($goods->count() === 0) {
            return;
        }

        $price = $goods->getPrices()->sum();

        if ($customerGroup->getMinimumOrderAmount() <= $price->getTotalPrice()) {
            return;
        }

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        $surcharge = $this->priceCalculator->calculate(
            new PriceDefinition($customerGroup->getMinimumOrderAmountSurcharge(), $rules, 1, true),
            $context
        );

        $calculatedCart->getCalculatedLineItems()->add(new Discount(
            'minimum-order-value',
            $surcharge,
            sprintf('Minimum order value of %s', $customerGroup->getMinimumOrderAmount())
        ));
    }
}