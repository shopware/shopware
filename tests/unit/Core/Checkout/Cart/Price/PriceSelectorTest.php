<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setPriceBasis($basis);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);
        $context->method('getTaxState')->willReturn($taxState);

        $selected = (new PriceSelector())->select(new Price(Defaults::CURRENCY, 10.0, 11.9, false), $context);

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

        yield 'unknown basis falls back to the legacy display driven selection' => [
            'gross',
            CartPrice::TAX_STATE_GROSS,
            11.9,
            true,
        ];
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $selector = new PriceSelector();

        $this->expectExceptionObject(new DecorationPatternException(PriceSelector::class));

        $selector->getDecorated();
    }
}
