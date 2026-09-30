<?php declare(strict_types=1);

namespace Acme\Tests\Unit\Some\ThirdParty;

use PHPUnit\Framework\TestCase;

/**
 * Outside the first-party test namespaces: the rule must stay silent even for the banned shape.
 *
 * @internal
 */
class ForeignNamespaceCases extends TestCase
{
    public function testTrailingAssertOutsideFirstPartyNamespaces(): void
    {
        $items = new \ArrayObject(['a']);

        $this->expectException(\RuntimeException::class);

        self::throwingCall($items);

        static::assertCount(1, $items);
    }

    public static function throwingCall(\ArrayObject $items): void
    {
        throw new \RuntimeException('boom: ' . \count($items));
    }
}
