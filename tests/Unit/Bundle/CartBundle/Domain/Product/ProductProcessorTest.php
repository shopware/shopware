<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Domain\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Price\PriceDefinitionCollection;
use Shopware\Cart\Price\PriceRounding;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\CartBridge\Product\ProductCalculator;
use Shopware\CartBridge\Product\ProductData;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\TaxRuleCalculator;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\CartBridge\Product\ProductPriceGateway;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopContext;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\Generator;

class ProductProcessorTest extends TestCase
{
    const DUMMY_TAX_NAME = 'test-tax';

    public function testConvertingAnEmptyCart(): void
    {
        $calculator = $this->createMock(PriceCalculator::class);
        $calculator
            ->expects(static::never())
            ->method('calculate');

        $productCalculator = new ProductCalculator($calculator);
        $processor = new ProductProcessor($productCalculator);

        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );

        $processor->process(
            CartContainer::createExisting('test', 'test', []),
            $processorCart,
            new StructCollection(),
            Generator::createContext()
        );

        static::assertCount(0, $processorCart->getCalculatedLineItems());
    }

    public function testConvertOneProduct(): void
    {
        $calculator = $this->createMock(PriceCalculator::class);
        $calculator
            ->expects(static::once())
            ->method('calculate')
            ->will(static::returnValue(
                new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 0)
            ));

        $productCalculator = new ProductCalculator($calculator);
        $processor = new ProductProcessor($productCalculator);

        $cart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );

        $processor->process(
            CartContainer::createExisting('test', 'test', [
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            $cart,
            new StructCollection([
                'SW1' => new ProductData(
                    'SW1',
                    new PriceDefinitionCollection([
                        new PriceDefinition(1, new TaxRuleCollection(), 1),
                    ]),
                    new DefaultDeliveryInformation(),
                    new AndRule()
                ),
            ]),
            Generator::createContext()
        );

        static::assertCount(1, $cart->getCalculatedLineItems());

        static::assertEquals(
            new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
            $cart->getCalculatedLineItems()->get('SW1')->getLineItem()
        );
    }

    public function testConvertMultipleProducts(): void
    {
        $calculator = $this->createMock(PriceCalculator::class);
        $calculator
            ->expects(static::any())
            ->method('calculate')
            ->will(static::returnValue(
                new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 0)
            ));

        $productCalculator = new ProductCalculator($calculator);
        $processor = new ProductProcessor($productCalculator);

        $cart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );

        $data = new StructCollection([
            'SW1' => new ProductData('SW1', new PriceDefinitionCollection([new PriceDefinition(0, new TaxRuleCollection())]), new DefaultDeliveryInformation(), new AndRule()),
            'SW2' => new ProductData('SW2', new PriceDefinitionCollection([new PriceDefinition(0, new TaxRuleCollection())]), new DefaultDeliveryInformation(), new AndRule()),
            'SW3' => new ProductData('SW3', new PriceDefinitionCollection([new PriceDefinition(0, new TaxRuleCollection())]), new DefaultDeliveryInformation(), new AndRule()),
        ]);

        $processor->process(
            CartContainer::createExisting('test', 'test', [
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW3', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            $cart,
            $data,
            Generator::createContext()
        );

        static::assertEquals(
            new CalculatedLineItemCollection([
                new CalculatedProduct(
                    'SW1',
                    1,
                    new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 0),
                    new DefaultDeliveryInformation(),
                    new AndRule()
                ),
                new CalculatedProduct(
                    'SW2',
                    1,
                    new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 0),
                    new DefaultDeliveryInformation(),
                    new AndRule()
                ),
                new CalculatedProduct(
                    'SW3',
                    1,
                    new LineItem('SW3', ProductProcessor::TYPE_PRODUCT, 1),
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 0),
                    new DefaultDeliveryInformation(),
                    new AndRule()
                ),
            ]),
            $cart->getCalculatedLineItems()
        );
    }

    public function testWithoutData(): void
    {
        $priceCalculator = $this->createMock(PriceCalculator::class);
        $priceCalculator->method('calculate')->will($this->returnValue(
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection())
        ));

        $productCalculator = new ProductCalculator($priceCalculator);

        $lineItemCollection = new LineItemCollection([
            new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
        ]);

        $context = $this->createMock(ShopContext::class);

        $data = new StructCollection([]);

        $productCollection = $productCalculator->calculate($lineItemCollection, $context, $data);

        $this->assertSame(0, $productCollection->count());
    }

    public function testWithPreDefinedPrice(): void
    {
        $taxDetector = $this->createMock(TaxDetector::class);
        $taxDetector->method('useGross')->will($this->returnValue(true));

        $priceGateway = $this->createMock(ProductPriceGateway::class);
        $priceGateway->method('get')->will($this->returnValue(new PriceDefinitionCollection()));

        $calculator = new ProductCalculator(
            new PriceCalculator(
                new TaxCalculator(
                    new PriceRounding(2),
                    [new TaxRuleCalculator(new PriceRounding(2))]
                ),
                new PriceRounding(2),
                $taxDetector
            )
        );

        $context = $this->createMock(ShopContext::class);
        $lineItems = new LineItemCollection([
            new LineItem(
                'sw1',
                ProductProcessor::TYPE_PRODUCT,
                5,
                [],
                new PriceDefinition(5, new TaxRuleCollection([new TaxRule(19)]), 5, true)
            ),
        ]);

        $data = new StructCollection([
            'sw1' => new ProductData(
                'sw1',
                new PriceDefinitionCollection(),
                new DefaultDeliveryInformation(),
                new AndRule()
            ),
        ]);

        /** @var \Shopware\Context\Struct\ShopContext $context */
        $products = $calculator->calculate($lineItems, $context, $data);

        $this->assertSame(1, $products->count());

        $this->assertTrue($products->has('sw1'));

        $product = $products->get('sw1');

        $this->assertEquals(
            new Price(
                5,
                25,
                new CalculatedTaxCollection([new CalculatedTax(3.99, 19, 25)]),
                new TaxRuleCollection([new TaxRule(19)]),
                5
            ),
            $product->getPrice()
        );
    }
}

class DefaultDeliveryInformation extends DeliveryInformation
{
    public function __construct()
    {
        parent::__construct(
            0,
            0,
            0,
            0,
            0,
            new DeliveryDate(
                new \DateTime(),
                new \DateTime()
            ),
            new DeliveryDate(
                new \DateTime(),
                new \DateTime()
            )
        );
    }
}
