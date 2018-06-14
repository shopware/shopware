<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Delivery;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Core\Checkout\Cart\Price\PriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
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
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        Connection $connection,
        PriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->connection = $connection;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(Delivery $delivery, CheckoutContext $context): void
    {
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

    private function calculateShippingCosts(float $price, CalculatedLineItemCollection $calculatedLineItems, CheckoutContext $context): CalculatedPrice
    {
        $rules = $this->percentageTaxRuleBuilder->buildRules(
            $calculatedLineItems->getPrices()->sum()
        );

        $definition = new PriceDefinition($price, $rules, 1, true);

        return $this->priceCalculator->calculate($definition, $context);
    }

    private function findShippingCosts(ShippingMethodBasicStruct $shippingMethod, float $value, Context $context): float
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('costs.price');
        $query->from('shipping_method_price', 'costs');
        $query->andWhere('costs.`quantity_from` <= :value');
        $query->andWhere('costs.shipping_method_id = :id');
        $query->andWhere('costs.tenant_id = :tenant');
        $query->setParameter('id', $shippingMethod->getId());
        $query->setParameter('value', $value);
        $query->setParameter('tenant', Uuid::fromHexToBytes($context->getTenantId()));
        $query->addOrderBy('price', 'DESC');
        $query->setMaxResults(1);

        return (float) $query->execute()->fetch(\PDO::FETCH_COLUMN);
    }
}
