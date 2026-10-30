<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection as TaxRuleEntityCollection;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelContext::class)]
class SalesChannelContextTest extends TestCase
{
    protected function tearDown(): void
    {
        // reset field visibility, see `testGetTokenIsNotAccessibleFromTwigRenderingContext()` test
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testGetRuleIdsByAreas(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();
        $idD = Uuid::randomHex();

        $areaRuleIds = [
            'a' => [$idA, $idB],
            'b' => [$idA, $idC, $idD],
            'c' => [$idB],
            'd' => [$idC],
        ];

        $salesChannelContext->setAreaRuleIds($areaRuleIds);

        static::assertSame($areaRuleIds, $salesChannelContext->getAreaRuleIds());

        static::assertSame([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a']));
        static::assertSame([$idA, $idB, $idC, $idD], $salesChannelContext->getRuleIdsByAreas(['a', 'b']));
        static::assertSame([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a', 'c']));
        static::assertSame([$idC], $salesChannelContext->getRuleIdsByAreas(['d']));
        static::assertSame([], $salesChannelContext->getRuleIdsByAreas(['f']));
        static::assertSame([
            'extensions' => [],
            'system' => 'metric',
            'units' => [
                'length' => 'mm',
                'weight' => 'kg',
            ],
        ], $salesChannelContext->getMeasurementSystem()->jsonSerialize());

        $newMeasurementSystem = new MeasurementUnits(
            'imperial',
            [
                'length' => 'in',
                'weight' => 'lb',
            ]
        );

        $salesChannelContext->setMeasurementSystem($newMeasurementSystem);
        static::assertSame([
            'extensions' => [],
            'system' => 'imperial',
            'units' => [
                'length' => 'in',
                'weight' => 'lb',
            ],
        ], $salesChannelContext->getMeasurementSystem()->jsonSerialize());
    }

    public function testGetRuleIdsByAreasDeduplicatesWithinAndAcrossAreas(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();

        $salesChannelContext->setAreaRuleIds([
            // duplicate id within a single area must be collapsed
            'a' => [$idA, $idB, $idA],
            'b' => [$idB, $idC],
        ]);

        // within-area dedup, insertion order preserved
        static::assertSame([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a']));
        // cross-area dedup ($idB appears in both areas), first-occurrence order preserved
        static::assertSame([$idA, $idB, $idC], $salesChannelContext->getRuleIdsByAreas(['a', 'b']));
        // the result is a sequentially indexed list (no gaps from dedup)
        static::assertSame([0, 1, 2], array_keys($salesChannelContext->getRuleIdsByAreas(['a', 'b'])));
    }

    public function testWithPermissions(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $permissionsBefore = $salesChannelContext->getPermissions();
        static::assertSame([], $permissionsBefore);

        $called = false;
        $salesChannelContext->withPermissions(
            [CheckoutPermissions::PERSIST_CART_ERRORS => true],
            static function (SalesChannelContext $context) use (&$called): void {
                $called = true;

                static::assertTrue($context->hasPermission(CheckoutPermissions::PERSIST_CART_ERRORS));
            },
        );

        static::assertTrue($called);
        $permissionsAfter = $salesChannelContext->getPermissions();
        static::assertSame([], $permissionsAfter);
    }

    public function testWithPermissionsWithLockedPermissions(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->lockPermissions();
        $permissionsBefore = $salesChannelContext->getPermissions();
        static::assertSame([], $permissionsBefore);

        $called = false;
        $salesChannelContext->withPermissions(
            [CheckoutPermissions::PERSIST_CART_ERRORS => true],
            static function (SalesChannelContext $context) use (&$called): void {
                $called = true;

                static::assertCount(0, $context->getPermissions());
            },
        );

        static::assertTrue($called);
        $permissionsAfter = $salesChannelContext->getPermissions();
        static::assertSame([], $permissionsAfter);
    }

    public function testSalesChannelContextStateFunctionPassesResetsAndKeepsState(): void
    {
        $manualState = 'manual-state';
        $closureState = 'closure-state';

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->addState($manualState);

        static::assertTrue($salesChannelContext->hasState($manualState));
        static::assertTrue($salesChannelContext->getContext()->hasState($manualState));
        static::assertFalse($salesChannelContext->hasState($closureState));
        static::assertFalse($salesChannelContext->getContext()->hasState($closureState));

        $closureStates = $salesChannelContext->state(static function (SalesChannelContext $closureContext): array {
            return $closureContext->getStates();
        }, $closureState);

        static::assertContains($closureState, $closureStates);
        static::assertContains($manualState, $closureStates);
        static::assertTrue($salesChannelContext->hasState($manualState));
        static::assertTrue($salesChannelContext->getContext()->hasState($manualState));
        static::assertFalse($salesChannelContext->hasState($closureState));
        static::assertFalse($salesChannelContext->getContext()->hasState($closureState));
    }

    public function testGetTokenIsNotAccessibleFromTwigRenderingContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        // outside of twig rendering context, token is accessible
        $salesChannelContext->getToken();

        // fake twig rendering context, see `\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute()`
        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(SalesChannelException::contextTokenNotAccessible());
        $salesChannelContext->getToken();
    }

    public function testDigitalProductsUseTheDigitalTaxRules(): void
    {
        $context = $this->createContext(digitalTaxRules: $this->createTaxes(21.0));

        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0)]), $context->buildTaxRules(Generator::TAX));
        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0)]), $context->buildProductTaxRules(Generator::TAX, ProductDefinition::TYPE_PHYSICAL));
        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0)]), $context->buildProductTaxRules(Generator::TAX, 'custom-type'));
        static::assertEquals(new TaxRuleCollection([new TaxRule(21.0)]), $context->buildProductTaxRules(Generator::TAX, ProductDefinition::TYPE_DIGITAL));
    }

    public function testDigitalProductsFallBackToTheShippingLocation(): void
    {
        $context = $this->createContext();

        static::assertSame($context->getShippingLocation(), $context->getBillingLocation());
        static::assertSame($context->getTaxRules(), $context->getDigitalTaxRules());
    }

    public function testBillingLocationIsUsedWhenGiven(): void
    {
        $billingLocation = ShippingLocation::createFromCountry(new CountryEntity());

        static::assertSame($billingLocation, $this->createContext(billingLocation: $billingLocation)->getBillingLocation());
    }

    /**
     * @param list<string> $taxFreeProductTypes
     */
    #[DataProvider('taxFreeProductTypeProvider')]
    public function testTaxFreeProductTypeIsTaxedAtZeroPercent(array $taxFreeProductTypes, string $productType, bool $isTaxFree): void
    {
        $context = $this->createContext(digitalTaxRules: $this->createTaxes(21.0));
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);
        $context->setTaxFreeProductTypes($taxFreeProductTypes);

        static::assertSame($taxFreeProductTypes, $context->getTaxFreeProductTypes());
        static::assertSame($isTaxFree ? CartPrice::TAX_STATE_FREE : CartPrice::TAX_STATE_GROSS, $context->getProductTaxState($productType));

        $taxRate = $productType === ProductDefinition::TYPE_DIGITAL ? 21.0 : 19.0;
        static::assertEquals(
            new TaxRuleCollection([new TaxRule($isTaxFree ? 0.0 : $taxRate)]),
            $context->buildProductTaxRules(Generator::TAX, $productType),
        );
    }

    /**
     * @return iterable<string, array{list<string>, string, bool}>
     */
    public static function taxFreeProductTypeProvider(): iterable
    {
        yield 'exempt digital product' => [[ProductDefinition::TYPE_DIGITAL], ProductDefinition::TYPE_DIGITAL, true];
        yield 'physical product next to exempt digital products' => [[ProductDefinition::TYPE_DIGITAL], ProductDefinition::TYPE_PHYSICAL, false];
        yield 'exempt physical product' => [[ProductDefinition::TYPE_PHYSICAL], ProductDefinition::TYPE_PHYSICAL, true];
        yield 'custom product type is exempted like physical goods' => [[ProductDefinition::TYPE_PHYSICAL], 'custom-type', true];
        yield 'digital product next to exempt physical products' => [[ProductDefinition::TYPE_PHYSICAL], ProductDefinition::TYPE_DIGITAL, false];
        yield 'nothing exempt' => [[], ProductDefinition::TYPE_DIGITAL, false];
    }

    public function testTaxFreeCartKeepsTheTaxRules(): void
    {
        $context = $this->createContext();
        $context->setTaxState(CartPrice::TAX_STATE_FREE);

        static::assertSame(CartPrice::TAX_STATE_FREE, $context->getProductTaxState(ProductDefinition::TYPE_DIGITAL));
        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0)]), $context->buildProductTaxRules(Generator::TAX, ProductDefinition::TYPE_DIGITAL));
    }

    private function createContext(?ShippingLocation $billingLocation = null, ?TaxCollection $digitalTaxRules = null): SalesChannelContext
    {
        return Generator::generateSalesChannelContext(
            taxRules: $this->createTaxes(19.0),
            overrides: ['billingLocation' => $billingLocation, 'digitalTaxRules' => $digitalTaxRules],
        );
    }

    private function createTaxes(float $taxRate): TaxCollection
    {
        $tax = new TaxEntity();
        $tax->setId(Generator::TAX);
        $tax->setTaxRate($taxRate);
        $tax->setRules(new TaxRuleEntityCollection());

        return new TaxCollection([$tax]);
    }
}
