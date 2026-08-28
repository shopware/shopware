<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AbsolutePriceDefinition::class)]
class AbsolutePriceDefinitionTest extends TestCase
{
    public function testGettersExposeTheConstructorValues(): void
    {
        $filter = new AndRule();
        $definition = new AbsolutePriceDefinition(10.5, $filter);

        static::assertSame(10.5, $definition->getPrice());
        static::assertSame($filter, $definition->getFilter());
        static::assertSame(AbsolutePriceDefinition::TYPE, $definition->getType());
        static::assertSame(AbsolutePriceDefinition::SORTING_PRIORITY, $definition->getPriority());
    }

    public function testJsonSerializeAddsTheType(): void
    {
        $data = (new AbsolutePriceDefinition(10.0))->jsonSerialize();

        static::assertSame('absolute', $data['type']);
        static::assertSame(10.0, $data['price']);
        static::assertNull($data['filter']);
    }
}
