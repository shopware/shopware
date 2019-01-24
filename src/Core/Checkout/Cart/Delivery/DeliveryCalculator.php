<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;

class DeliveryCalculator
{
    public const CALCULATION_BY_WEIGHT = 0;

    public const CALCULATION_BY_PRICE = 1;

    public const CALCULATION_BY_LINE_ITEM_COUNT = 2;

    public const CALCULATION_BY_CUSTOM = 3;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        Connection $connection,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->connection = $connection;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(DeliveryCollection $deliveries, CheckoutContext $context): void
    {
        foreach ($deliveries as $delivery) {
            $this->calculateDelivery($delivery, $context);
        }
    }

    private function calculateDelivery(Delivery $delivery, CheckoutContext $context): void
    {
        if ($delivery->getShippingCosts()->getUnitPrice() > 0) {
            $costs = $this->calculateShippingCosts(
                $delivery->getShippingCosts()->getTotalPrice(),
                $delivery->getPositions()->getLineItems(),
                $context
            );

            $delivery->setShippingCosts($costs);

            return;
        }

        switch ($delivery->getShippingMethod()->getCalculation()) {
            case self::CALCULATION_BY_WEIGHT:
                $costs = $this->calculateShippingCosts(
                    $this->findShippingCosts(
                        $delivery->getShippingMethod(),
                        $delivery->getPositions()->getWeight(),
                        $context->getContext()
                    ),
                    $delivery->getPositions()->getLineItems(),
                    $context
                );

                break;
            case self::CALCULATION_BY_PRICE:
                $costs = $this->calculateShippingCosts(
                    $this->findShippingCosts(
                        $delivery->getShippingMethod(),
                        $delivery->getPositions()->getPrices()->sum()->getTotalPrice(),
                        $context->getContext()
                    ),
                    $delivery->getPositions()->getLineItems(),
                    $context
                );

                break;

            case self::CALCULATION_BY_LINE_ITEM_COUNT:
                $costs = $this->calculateShippingCosts(
                    $this->findShippingCosts(
                        $delivery->getShippingMethod(),
                        $delivery->getPositions()->getQuantity(),
                        $context->getContext()
                    ),
                    $delivery->getPositions()->getLineItems(),
                    $context
                );
                break;

            case self::CALCULATION_BY_CUSTOM:
                return;
            default:
                $price = $delivery->getPositions()->getLineItems()->getPrices()->sum()->getTotalPrice() / 100;
                $costs = $this->calculateShippingCosts(
                    $price,
                    $delivery->getPositions()->getLineItems(),
                    $context
                );
        }

        $delivery->setShippingCosts($costs);
    }

    private function calculateShippingCosts(float $price, LineItemCollection $calculatedLineItems, CheckoutContext $context): CalculatedPrice
    {
        $rules = $this->percentageTaxRuleBuilder->buildRules(
            $calculatedLineItems->getPrices()->sum()
        );

        $definition = new QuantityPriceDefinition($price, $rules, 1, true);

        return $this->priceCalculator->calculate($definition, $context);
    }

    private function findShippingCosts(ShippingMethodEntity $shippingMethod, float $value, Context $context): float
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('costs.price');
        $query->from('shipping_method_price', 'costs');
        $query->andWhere('costs.`quantity_from` <= :value');
        $query->andWhere('costs.shipping_method_id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($shippingMethod->getId()));
        $query->setParameter('value', $value);
        $query->addOrderBy('price', 'DESC');
        $query->setMaxResults(1);

        return (float) $query->execute()->fetchColumn();
    }
}
