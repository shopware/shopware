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

namespace Shopware\Cart\Test\Domain\Voucher;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Cart\Delivery\DeliveryCollection;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Error\VoucherNotFoundError;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Product\ProductProcessor;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Cart\Test\Common\DummyProduct;
use Shopware\Cart\Voucher\AbsoluteVoucherData;
use Shopware\Cart\Voucher\CalculatedVoucher;
use Shopware\Cart\Voucher\PercentageVoucherData;
use Shopware\Cart\Voucher\VoucherProcessor;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;

class VoucherProcessorTest extends TestCase
{
    public function testEmptyCart(): void
    {
        $cart = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection(),
            new ErrorCollection()
        );
        $processor = new VoucherProcessor(
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(PriceCalculator::class),
            new PercentageTaxRuleBuilder()
        );

        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );
        $processor->process(
            $cart,
            $processorCart,
            new IndexedCollection(),
            $this->createMock(ShopContext::class)
        );

        $this->assertSame(0, $cart->getErrors()->count());
        $this->assertSame(0, $processorCart->getCalculatedLineItems()->count());
    }

    public function testCartWithNotVoucher(): void
    {
        $processor = new VoucherProcessor(
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(PriceCalculator::class),
            new PercentageTaxRuleBuilder()
        );

        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([
                new DummyProduct('SW1'),
                new DummyProduct('SW2'),
            ]),
            new DeliveryCollection()
        );

        $cart = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection([
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            new ErrorCollection()
        );

        $processor->process(
            $cart,
            $processorCart,
            new IndexedCollection(),
            $this->createMock(ShopContext::class)
        );

        $this->assertSame(0, $cart->getErrors()->count());
        $this->assertSame(2, $processorCart->getCalculatedLineItems()->count());
    }

    public function testCartWithVoucherAndNoGoods(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([]),
            new DeliveryCollection()
        );

        $processor = new VoucherProcessor(
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(PriceCalculator::class),
            new PercentageTaxRuleBuilder()
        );

        $cart = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection([
                new LineItem('voucher', VoucherProcessor::TYPE_VOUCHER, 1, ['code' => 'test']),
            ]),
            new ErrorCollection()
        );

        $data = new IndexedCollection();
        $processor->process($cart, $processorCart, $data, $this->createMock(ShopContext::class));

        $this->assertSame(0, $cart->getErrors()->count());
        $this->assertSame(0, $processorCart->getCalculatedLineItems()->count());
    }

    public function testVoucherNotExists(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([
                new DummyProduct('SW1'),
                new DummyProduct('SW2'),
            ]),
            new DeliveryCollection()
        );

        $cart = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection([
                new LineItem('voucher', VoucherProcessor::TYPE_VOUCHER, 1, ['code' => 'test']),
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            new ErrorCollection()
        );

        $processor = new VoucherProcessor(
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(PriceCalculator::class),
            new PercentageTaxRuleBuilder()
        );

        $data = new IndexedCollection();
        $processor->process($cart, $processorCart, $data, $this->createMock(ShopContext::class));

        $this->assertSame(1, $cart->getErrors()->count());
        $this->assertEquals(
            new ErrorCollection([new VoucherNotFoundError('test')]),
            $cart->getErrors()
        );

        /** @var VoucherNotFoundError $error */
        $error = $cart->getErrors()->get(0);

        $this->assertSame('Voucher with code test not found', $error->getMessage());
        $this->assertSame(VoucherNotFoundError::LEVEL_ERROR, $error->getLevel());
        $this->assertSame(VoucherNotFoundError::class, $error->getMessageKey());
    }

    public function testPercentage(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([
                new DummyProduct('SW1'),
                new DummyProduct('SW2'),
            ]),
            new DeliveryCollection()
        );

        $lineItem = new LineItem('voucher', VoucherProcessor::TYPE_VOUCHER, 1, ['code' => 'test']);

        $cartContainer = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection([
                $lineItem,
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            new ErrorCollection()
        );

        $price = new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection());
        $percentageCalculator = $this->createMock(PercentagePriceCalculator::class);
        $percentageCalculator->expects($this->once())->method('calculate')->will(
            $this->returnValue($price)
        );

        $processor = new VoucherProcessor(
            $percentageCalculator,
            $this->createMock(PriceCalculator::class),
            new PercentageTaxRuleBuilder()
        );

        $data = new IndexedCollection([
            'test' => new PercentageVoucherData('test', new AndRule(), 10),
        ]);
        $processor->process($cartContainer, $processorCart, $data, $this->createMock(ShopContext::class));
        $this->assertSame(1, $processorCart->getCalculatedLineItems()->filterInstance(CalculatedVoucher::class)->count());

        /** @var CalculatedVoucher $voucher */
        $voucher = $processorCart->getCalculatedLineItems()->get('voucher');
        $this->assertSame('voucher', $voucher->getIdentifier());
        $this->assertSame($lineItem, $voucher->getLineItem());
        $this->assertSame(1, $voucher->getQuantity());
        $this->assertSame($price, $voucher->getPrice());
        $this->assertSame($voucher, $voucher->getCalculatedLineItem());
        $this->assertSame('voucher', $voucher->getLabel());
        $this->assertNull($voucher->getCover());
        $this->assertSame('voucher', $voucher->getCode());
    }

    public function testAbsolute(): void
    {
        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection([
                new DummyProduct('SW1'),
                new DummyProduct('SW2'),
            ]),
            new DeliveryCollection()
        );

        $lineItem = new LineItem('voucher', VoucherProcessor::TYPE_VOUCHER, 1, ['code' => 'test']);

        $cartContainer = new CartContainer(
            'test',
            Uuid::uuid4()->toString(),
            new LineItemCollection([
                $lineItem,
                new LineItem('SW1', ProductProcessor::TYPE_PRODUCT, 1),
                new LineItem('SW2', ProductProcessor::TYPE_PRODUCT, 1),
            ]),
            new ErrorCollection()
        );

        $percentageCalculator = $this->createMock(PercentagePriceCalculator::class);
        $percentageCalculator->expects($this->never())->method('calculate');

        $priceCalculator = $this->createMock(PriceCalculator::class);
        $priceCalculator->expects($this->once())->method('calculate')->will(
            $this->returnValue(
                new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection())
            )
        );

        $processor = new VoucherProcessor(
            $percentageCalculator,
            $priceCalculator,
            new PercentageTaxRuleBuilder()
        );

        $data = new IndexedCollection();
        $data->add(new AbsoluteVoucherData('test', new AndRule(), new PriceDefinition(1, new TaxRuleCollection())), 'test');
        $processor->process($cartContainer, $processorCart, $data, $this->createMock(ShopContext::class));

        $this->assertSame(1, $processorCart->getCalculatedLineItems()->filterInstance(CalculatedVoucher::class)->count());
    }
}
