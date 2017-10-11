<?php declare(strict_types=1);
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

namespace Shopware\Cart\Test\Domain\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Cart\CalculatedCartGenerator;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\Delivery\Delivery;
use Shopware\Cart\Delivery\DeliveryCalculator;
use Shopware\Cart\Delivery\DeliveryCollection;
use Shopware\Cart\Delivery\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryPositionCollection;
use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Error\VoucherNotFoundError;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Price\AmountCalculator;
use Shopware\Cart\Price\CartPrice;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Cart\Test\Common\DummyProduct;
use Shopware\Context\Struct\ShopContext;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class CalculatedCartGeneratorTest extends TestCase
{
    public function test(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );

        $price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->method('calculateAmount')->will($this->returnValue($price));

        $generator = new CalculatedCartGenerator($amountCalculator);

        $container = CartContainer::createNew('test');

        $context = $this->createMock(ShopContext::class);

        $this->assertCalculatedCart(
            new CalculatedCart(
                $container,
                new CalculatedLineItemCollection(),
                $price,
                new DeliveryCollection()
            ),
            $generator->create($container, $context, $processorCart)
        );
    }

    public function testUsesLineItemsOfProcessorCart(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([
                new DummyProduct('SW1'),
                new DummyProduct('SW2'),
            ]),
            new DeliveryCollection()
        );

        $price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->method('calculateAmount')->will($this->returnValue($price));

        $generator = new CalculatedCartGenerator($amountCalculator);

        $container = CartContainer::createNew('test');

        $context = $this->createMock(ShopContext::class);

        $this->assertCalculatedCart(
            new CalculatedCart(
                $container,
                new CalculatedLineItemCollection([
                    new DummyProduct('SW1'),
                    new DummyProduct('SW2'),
                ]),
                $price,
                new DeliveryCollection()
            ),
            $generator->create($container, $context, $processorCart)
        );
    }

    public function testUsesDeliveriesOfProcessorCart(): void
    {
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new ShippingMethodBasicStruct(),
            $this->createMock(ShippingLocation::class),
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection([$delivery])
        );

        $price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->method('calculateAmount')->will($this->returnValue($price));

        $generator = new CalculatedCartGenerator($amountCalculator);

        $container = CartContainer::createNew('test');

        $context = $this->createMock(ShopContext::class);

        $this->assertCalculatedCart(
            new CalculatedCart(
                $container,
                new CalculatedLineItemCollection(),
                $price,
                new DeliveryCollection([$delivery])
            ),
            $generator->create($container, $context, $processorCart)
        );
    }

    public function testUsesErrorsOfProcessorCart(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );
        $container = CartContainer::createNew('test');

        $container->getErrors()->add(new VoucherNotFoundError('1'));

        $price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        $amountCalculator = $this->createMock(AmountCalculator::class);
        $amountCalculator->method('calculateAmount')->will($this->returnValue($price));

        $generator = new CalculatedCartGenerator($amountCalculator);

        $context = $this->createMock(ShopContext::class);

        $this->assertCalculatedCart(
            new CalculatedCart(
                $container,
                new CalculatedLineItemCollection(),
                $price,
                new DeliveryCollection()
            ),
            $generator->create($container, $context, $processorCart)
        );
    }

    private function assertCalculatedCart(CalculatedCart $expected, CalculatedCart $actual): void
    {
        $this->assertEquals($expected->getErrors(), $actual->getErrors());
        $this->assertEquals($expected->getName(), $actual->getName());
        $this->assertEquals($expected->getCartContainer(), $actual->getCartContainer());
        $this->assertEquals($expected->getCalculatedLineItems(), $actual->getCalculatedLineItems());
        $this->assertEquals($expected->getPrice(), $actual->getPrice());
        $this->assertEquals($expected->getDeliveries(), $actual->getDeliveries());
        $this->assertEquals($expected->getToken(), $actual->getToken());
        $this->assertEquals($expected, $actual);
    }
}
