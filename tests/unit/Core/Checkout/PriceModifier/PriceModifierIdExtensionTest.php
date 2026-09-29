<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\PriceModifier\PriceModifierIdExtension;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceModifierIdExtension::class)]
class PriceModifierIdExtensionTest extends TestCase
{
    public function testIsEmptyByDefault(): void
    {
        $extension = new PriceModifierIdExtension();

        static::assertSame([], $extension->getIds());
        static::assertFalse($extension->has('a'));
        static::assertSame('cart_price_modifier_id_extension', $extension->getApiAlias());
    }

    public function testAddKeepsInsertionOrderAndIgnoresDuplicates(): void
    {
        $extension = new PriceModifierIdExtension();

        $extension->add('a');
        $extension->add('b');
        $extension->add('a');

        static::assertSame(['a', 'b'], $extension->getIds());
        static::assertTrue($extension->has('a'));
        static::assertTrue($extension->has('b'));
    }

    public function testAddIgnoresEmptyId(): void
    {
        $extension = new PriceModifierIdExtension();

        $extension->add('');

        static::assertSame([], $extension->getIds());
        static::assertFalse($extension->has(''));
    }

    public function testRemoveReindexesRemainingIds(): void
    {
        $extension = new PriceModifierIdExtension();
        $extension->add('a');
        $extension->add('b');
        $extension->add('c');

        $extension->remove('a');

        static::assertSame(['b', 'c'], $extension->getIds());
        static::assertFalse($extension->has('a'));
    }

    public function testRemoveOfUnknownIdIsNoOp(): void
    {
        $extension = new PriceModifierIdExtension();
        $extension->add('a');

        $extension->remove('unknown');

        static::assertSame(['a'], $extension->getIds());
    }
}
