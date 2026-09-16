<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceDefinitionCollection::class)]
class PriceDefinitionCollectionTest extends TestCase
{
    public function testGetReturnsElementByIntKey(): void
    {
        $first = new QuantityPriceDefinition(10.0, new TaxRuleCollection(), 1);
        $second = new QuantityPriceDefinition(20.0, new TaxRuleCollection(), 2);

        $collection = new PriceDefinitionCollection([$first, $second]);

        static::assertSame($first, $collection->get(0));
        static::assertSame($second, $collection->get(1));
    }

    public function testGetCastsKeyToInt(): void
    {
        $definition = new QuantityPriceDefinition(10.0, new TaxRuleCollection(), 1);

        $collection = new PriceDefinitionCollection([$definition]);

        static::assertSame($definition, $collection->get('0'));
    }

    public function testGetReturnsNullOnMissingKey(): void
    {
        $collection = new PriceDefinitionCollection();

        static::assertNull($collection->get(0));
    }

    public function testGetApiAlias(): void
    {
        $collection = new PriceDefinitionCollection();

        static::assertSame('cart_price_definition_collection', $collection->getApiAlias());
    }
}
