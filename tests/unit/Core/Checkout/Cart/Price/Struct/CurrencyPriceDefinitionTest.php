<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection as RawPriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CurrencyPriceDefinition::class)]
class CurrencyPriceDefinitionTest extends TestCase
{
    public function testGettersExposeTheConstructorValues(): void
    {
        $prices = new RawPriceCollection([new Price('currency-id', 10.0, 12.0, false)]);
        $filter = new AndRule();
        $definition = new CurrencyPriceDefinition($prices, $filter);

        static::assertSame($prices, $definition->getPrice());
        static::assertSame($filter, $definition->getFilter());
        static::assertSame(CurrencyPriceDefinition::TYPE, $definition->getType());
        static::assertSame(CurrencyPriceDefinition::SORTING_PRIORITY, $definition->getPriority());
    }

    public function testJsonSerializeAddsTheType(): void
    {
        $prices = new RawPriceCollection();
        $data = (new CurrencyPriceDefinition($prices))->jsonSerialize();

        static::assertSame('currency-price', $data['type']);
        static::assertSame($prices, $data['price']);
        static::assertNull($data['filter']);
    }
}
