<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PercentagePriceDefinition::class)]
class PercentagePriceDefinitionTest extends TestCase
{
    public function testGettersExposeTheConstructorValues(): void
    {
        $filter = new AndRule();
        $definition = new PercentagePriceDefinition(-10.5, $filter);

        static::assertSame(-10.5, $definition->getPercentage());
        static::assertSame($filter, $definition->getFilter());
        static::assertSame(PercentagePriceDefinition::TYPE, $definition->getType());
        static::assertSame(PercentagePriceDefinition::SORTING_PRIORITY, $definition->getPriority());
    }

    public function testJsonSerializeAddsTheType(): void
    {
        $data = (new PercentagePriceDefinition(-10.0))->jsonSerialize();

        static::assertSame('percentage', $data['type']);
        static::assertSame(-10.0, $data['percentage']);
        static::assertNull($data['filter']);
    }

    public function testConstraintsRequireTheValue(): void
    {
        $constraints = PercentagePriceDefinition::getConstraints();

        static::assertSame(['percentage'], array_keys($constraints));
        static::assertCount(2, $constraints['percentage']);
    }

    public function testApiAlias(): void
    {
        static::assertSame('cart_price_percentage', (new PercentagePriceDefinition(-10.0))->getApiAlias());
    }
}
