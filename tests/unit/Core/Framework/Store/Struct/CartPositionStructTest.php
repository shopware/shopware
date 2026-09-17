<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\CartPositionStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartPositionStruct::class)]
class CartPositionStructTest extends TestCase
{
    public function testJsonSerializeOmitsTheExtensions(): void
    {
        $position = CartPositionStruct::fromArray(['netPrice' => 10.0, 'grossPrice' => 11.9]);

        $data = $position->jsonSerialize();

        static::assertArrayNotHasKey('extensions', $data);
        static::assertSame(10.0, $data['netPrice']);
        static::assertSame(11.9, $data['grossPrice']);
    }

    public function testAccessorsRoundTrip(): void
    {
        $position = new CartPositionStruct();

        $position->setNetPrice(10.0);
        $position->setTaxValue(1.9);
        $position->setGrossPrice(11.9);
        $position->setPseudoPrice(14.9);
        $position->setFirstMonthFree(true);
        $position->setDiscountAppliesForMonths(3);
        $position->setExtensionInformation(['id' => 7, 'name' => 'SwagExtension']);
        $position->setVariant(['id' => 11, 'name' => 'rent']);

        static::assertSame(10.0, $position->getNetPrice());
        static::assertSame(1.9, $position->getTaxValue());
        static::assertSame(11.9, $position->getGrossPrice());
        static::assertSame(14.9, $position->getPseudoPrice());
        static::assertTrue($position->isFirstMonthFree());
        static::assertSame(3, $position->getDiscountAppliesForMonths());
        static::assertSame(['id' => 7, 'name' => 'SwagExtension'], $position->getExtensionInformation());
        static::assertSame(7, $position->getExtensionId());
        static::assertSame('SwagExtension', $position->getExtensionName());
        static::assertSame(['id' => 11, 'name' => 'rent'], $position->getVariant());
        static::assertSame(11, $position->getVariantId());
        static::assertSame('rent', $position->getVariantType());
    }
}
