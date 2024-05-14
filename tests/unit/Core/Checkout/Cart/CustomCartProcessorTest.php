<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CustomCartProcessor;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CustomCartProcessor::class)]
#[Package('checkout')]
class CustomCartProcessorTest extends TestCase
{
    public function testCollect(): void
    {
        $data = new CartDataCollection();
        $original = $this->getCart();
        $context = Generator::createSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());

        $processor = new CustomCartProcessor($this->createMock(QuantityPriceCalculator::class));
        $processor->collect($data, $original, $context, $behavior);

        static::assertCount(4, $original->getLineItems());

        $lineItem1 = $original->getLineItems()->get('custom-1');
        static::assertNotNull($lineItem1);
        static::assertNotNull($lineItem1->getDeliveryInformation());
        $delivery1 = $lineItem1->getDeliveryInformation();
        static::assertSame(0.0, $delivery1->getWeight());
        static::assertFalse($delivery1->getFreeDelivery());

        $lineItem2 = $original->getLineItems()->get('custom-2');
        static::assertNotNull($lineItem2);
        static::assertNotNull($lineItem2->getDeliveryInformation());
        $delivery2 = $lineItem2->getDeliveryInformation();
        static::assertSame(0.0, $delivery2->getWeight());
        static::assertFalse($delivery2->getFreeDelivery());

        $lineItem3 = $original->getLineItems()->get('product-3');
        static::assertNotNull($lineItem3);
        static::assertNull($lineItem3->getDeliveryInformation());

        $lineItem4 = $original->getLineItems()->get('credit-4');
        static::assertNotNull($lineItem4);
        static::assertNull($lineItem4->getDeliveryInformation());
    }

    public function testProcess(): void
    {
        $data = new CartDataCollection();
        $original = $this->getCart();
        $toCalculate = new Cart('toCalculate');
        $context = Generator::createSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());

        $price = $original->getLineItems()->get('custom-1')?->getPriceDefinition();
        static::assertNotNull($price);

        $quantityPriceCalculator = $this->createMock(QuantityPriceCalculator::class);
        $quantityPriceCalculator
            ->expects(static::once())
            ->method('calculate')
            ->with($price, $context)
            ->willReturn(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $processor = new CustomCartProcessor($quantityPriceCalculator);
        $processor->process($data, $original, $toCalculate, $context, $behavior);

        static::assertCount(1, $toCalculate->getLineItems());
        static::assertEquals(5.0, $toCalculate->getLineItems()->get('custom-1')?->getPrice()?->getTotalPrice());
    }

    private function getCart(): Cart
    {
        $item1 = new LineItem('custom-1', 'custom', 'custom-1', 1);
        $item1->setPriceDefinition(new QuantityPriceDefinition(5.0, new TaxRuleCollection(), 1));

        $item2 = new LineItem('custom-2', 'custom', 'custom-2', 1);
        $item2->setPriceDefinition(new AbsolutePriceDefinition(5.0));

        $item3 = new LineItem('product-3', 'product', 'product-3', 1);
        $item4 = new LineItem('credit-4', 'credit', 'credit-4', 1);

        $cart = new Cart('hatoken');

        $cart->add($item1);
        $cart->add($item2);
        $cart->add($item3);
        $cart->add($item4);

        return $cart;
    }
}
