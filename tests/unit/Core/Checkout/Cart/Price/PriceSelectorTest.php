<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Extension\PriceSelectorExtension;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceSelector::class)]
class PriceSelectorTest extends TestCase
{
    #[DataProvider('priceBasisProvider')]
    public function testSelect(?string $basis, string $taxState, float $expectedValue, bool $expectedIsCalculated): void
    {
        $selected = $this->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            new TaxRuleCollection([new TaxRule(19)]),
            $basis,
            $taxState
        );

        static::assertSame($expectedValue, $selected->getValue());
        static::assertSame($expectedIsCalculated, $selected->isCalculated());
    }

    public static function priceBasisProvider(): \Generator
    {
        yield 'legacy basis follows the display mode and takes the stored gross' => [
            null,
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];

        yield 'legacy basis follows the display mode and takes the stored net' => [
            null,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'legacy basis takes the stored net for tax free deliveries' => [
            null,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'net basis hands the stored net to the gross calculator for derivation' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_GROSS,
            10.0,
            false,
        ];

        yield 'net basis takes the stored net as final value for net display' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'net basis takes the stored net as final value for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'gross basis takes the stored gross as final value for gross display' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];

        yield 'gross basis derives the net from the stored gross and ignores the stored net' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_NET,
            10.0,
            true,
        ];

        yield 'gross basis takes the stored net as final value for tax free deliveries' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_FREE,
            10.0,
            true,
        ];

        yield 'unknown basis falls back to the legacy display driven selection' => [
            'exact-net',
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];
    }

    #[DataProvider('taxStateOtherThanTheDisplayModeProvider')]
    public function testATaxStateOtherThanTheDisplayModeTakesItsStoredValue(string $basis, bool $displayGross, string $taxState, float $expectedValue): void
    {
        $selected = $this->select(
            new Price(Defaults::CURRENCY, 100.0, 119.0, false),
            new TaxRuleCollection([new TaxRule(20)]),
            $basis,
            $taxState,
            $displayGross
        );

        static::assertSame($expectedValue, $selected->getValue());
        static::assertTrue($selected->isCalculated());
    }

    public static function taxStateOtherThanTheDisplayModeProvider(): \Generator
    {
        yield 'gross basis matching the gross display keeps the stored net where an extension forces the net state' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            true,
            CartPrice::TAX_STATE_NET,
            100.0,
        ];

        yield 'net basis matching the net display keeps the stored gross where an order edit runs in the gross state' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            false,
            CartPrice::TAX_STATE_GROSS,
            119.0,
        ];

        yield 'net basis with gross display keeps the stored net in the net state' => [
            CustomerGroupEntity::PRICE_BASIS_NET,
            true,
            CartPrice::TAX_STATE_NET,
            100.0,
        ];

        yield 'gross basis with net display keeps the stored gross in the gross state' => [
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            false,
            CartPrice::TAX_STATE_GROSS,
            119.0,
        ];
    }

    public function testGrossBasisDerivesTheNetWithTheCountryTaxRate(): void
    {
        $price = new Price(Defaults::CURRENCY, 10.0, 11.9, false);

        $germany = $this->select($price, new TaxRuleCollection([new TaxRule(19)]), CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_NET);
        $austria = $this->select($price, new TaxRuleCollection([new TaxRule(20)]), CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_NET);

        // the fixed gross of 11.90 contains 1.90 tax in DE and 1.98 in AT, the net the merchant keeps varies with it
        static::assertSame(10.0, $germany->getValue());
        static::assertEqualsWithDelta(9.92, $austria->getValue(), 0.005);
    }

    public function testGrossBasisDerivesANetOverPartialTaxRulesThatIsTaxedBackToTheStoredGross(): void
    {
        $taxRules = new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]);

        $selected = $this->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            $taxRules,
            CustomerGroupEntity::PRICE_BASIS_GROSS,
            CartPrice::TAX_STATE_NET
        );

        static::assertEqualsWithDelta(11.9 / 1.13, $selected->getValue(), 0.000001);

        $addedTax = (new TaxCalculator())->calculateNetTaxes($selected->getValue(), $taxRules)->getAmount();
        static::assertEqualsWithDelta(11.9, $selected->getValue() + $addedTax, 0.000001);
    }

    public function testSelectPublishesTheExtensionWithItsInput(): void
    {
        $price = new Price(Defaults::CURRENCY, 10.0, 11.9, false);
        $taxRules = new TaxRuleCollection([new TaxRule(19)]);
        $context = $this->createContext(CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_GROSS);

        $published = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PriceSelectorExtension::onPost(), static function (PriceSelectorExtension $extension) use (&$published): void {
            $published = $extension;
        });

        $selected = $this->createSelector($dispatcher)->select($price, $taxRules, $context);

        static::assertInstanceOf(PriceSelectorExtension::class, $published);
        static::assertSame(['price' => $price, 'taxRules' => $taxRules, 'context' => $context], $published->getParams());
        static::assertSame($selected, $published->result);
    }

    public function testAPreListenerReplacesTheSelection(): void
    {
        $replacement = new SelectedPrice(9.99, isCalculated: true);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PriceSelectorExtension::onPre(), static function (PriceSelectorExtension $extension) use ($replacement): void {
            $extension->result = $replacement;
            $extension->stopPropagation();
        });

        $selected = $this->createSelector($dispatcher)->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            new TaxRuleCollection([new TaxRule(19)]),
            $this->createContext(CustomerGroupEntity::PRICE_BASIS_NET, CartPrice::TAX_STATE_GROSS)
        );

        static::assertSame($replacement, $selected);
    }

    public function testAPostListenerAdjustsTheSelection(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PriceSelectorExtension::onPost(), static function (PriceSelectorExtension $extension): void {
            $extension->result = new SelectedPrice(floor($extension->result->getValue()) + 0.99, isCalculated: true);
        });

        $selected = $this->createSelector($dispatcher)->select(
            new Price(Defaults::CURRENCY, 10.0, 11.9, false),
            new TaxRuleCollection([new TaxRule(19)]),
            $this->createContext(CustomerGroupEntity::PRICE_BASIS_GROSS, CartPrice::TAX_STATE_GROSS)
        );

        static::assertSame(11.99, $selected->getValue());
    }

    private function select(Price $price, TaxRuleCollection $taxRules, ?string $basis, string $taxState, ?bool $displayGross = null): SelectedPrice
    {
        return $this->createSelector(new EventDispatcher())->select($price, $taxRules, $this->createContext($basis, $taxState, $displayGross));
    }

    private function createSelector(EventDispatcherInterface $dispatcher): PriceSelector
    {
        return new PriceSelector(new TaxCalculator(), new ExtensionDispatcher($dispatcher));
    }

    private function createContext(?string $basis, string $taxState, ?bool $displayGross = null): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrentCustomerGroup')->willReturn((new CustomerGroupEntity())->assign([
            'priceBasis' => $basis,
            'displayGross' => $displayGross ?? $taxState === CartPrice::TAX_STATE_GROSS,
        ]));
        $context->method('getTaxState')->willReturn($taxState);

        return $context;
    }
}
