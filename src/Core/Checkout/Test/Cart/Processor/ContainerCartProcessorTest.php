<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor\ContainerCartProcessor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\AbsoluteItem;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\CalculatedTaxes;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\ContainerItem;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\HighTaxes;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\LowTaxes;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\PercentageItem;
use Shopware\Core\Checkout\Test\Cart\Processor\_fixtures\QuantityItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class ContainerCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider calculationProvider
     */
    public function testCalculation(LineItem $item, ?CalculatedPrice $expected): void
    {
        $processor = $this->getContainer()->get(ContainerCartProcessor::class);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([$item]));

        $new = new Cart('after');
        $processor->process(new CartDataCollection(), $cart, $new, $context, new CartBehavior());

        if ($expected === null) {
            static::assertFalse($new->has($item->getId()));

            return;
        }

        static::assertTrue($new->has($item->getId()));

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice());
        static::assertEquals($expected->getUnitPrice(), $item->getPrice()->getUnitPrice());
        static::assertEquals($expected->getTotalPrice(), $item->getPrice()->getTotalPrice());
        static::assertEquals($expected->getCalculatedTaxes()->getAmount(), $item->getPrice()->getCalculatedTaxes()->getAmount());

        foreach ($expected->getCalculatedTaxes() as $tax) {
            $actual = $item->getPrice()->getCalculatedTaxes()->get((string) $tax->getTaxRate());

            static::assertInstanceOf(CalculatedTax::class, $actual, sprintf('Missing tax for rate %f', $tax->getTaxRate()));
            static::assertEquals($tax->getTax(), $actual->getTax());
        }

        foreach ($item->getPrice()->getCalculatedTaxes() as $tax) {
            $actual = $expected->getCalculatedTaxes()->get((string) $tax->getTaxRate());

            static::assertInstanceOf(CalculatedTax::class, $actual, sprintf('Missing tax for rate %f', $tax->getTaxRate()));
            static::assertEquals($tax->getTax(), $actual->getTax());
        }
    }

    public static function calculationProvider(): \Generator
    {
        yield 'Test empty container will be removed' => [
            new ContainerItem(),
            null,
        ];

        yield 'Test container with one quantity price definition' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
            ]),
            new CalculatedPrice(20, 20, new CalculatedTaxes([19 => 3.19]), new HighTaxes()),
        ];

        yield 'Test percentage discount for one item' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new PercentageItem(-10),
            ]),
            new CalculatedPrice(18, 18, new CalculatedTaxes([19 => 2.87]), new HighTaxes()),
        ];

        yield 'Test absolute discount for one item' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new AbsoluteItem(-10),
            ]),
            new CalculatedPrice(10, 10, new CalculatedTaxes([19 => 1.59]), new HighTaxes()),
        ];

        yield 'Test discount calculation for two items' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),
            ]),
            new CalculatedPrice(36, 36, new CalculatedTaxes([19 => 2.87, 7 => 1.18]), new HighTaxes()),
        ];

        yield 'Test discount calculation with random order' => [
            new ContainerItem([
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),
                new QuantityItem(20, new HighTaxes()),
            ]),
            new CalculatedPrice(36, 36, new CalculatedTaxes([19 => 2.87, 7 => 1.18]), new HighTaxes()),
        ];

        yield 'Test nested calculation' => [
            new ContainerItem([ // 108,40€ - 10% = 97,56€
                new QuantityItem(20, new HighTaxes()),
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),

                new ContainerItem([ // 76€ - 10% = 68,40€
                    new QuantityItem(20, new HighTaxes()),
                    new QuantityItem(20, new LowTaxes()),

                    new ContainerItem([                             // 40 - 10% = 36€
                        new QuantityItem(20, new HighTaxes()),
                        new QuantityItem(20, new LowTaxes()),
                        new PercentageItem(-10),
                    ]),
                    new PercentageItem(-10),
                ]),
            ]),
            new CalculatedPrice(97.56, 97.56, new CalculatedTaxes([19 => 7.77, 7 => 3.20]), new HighTaxes()),
        ];
    }
}
