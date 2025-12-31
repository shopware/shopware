<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CloneTrait;

/**
 * @internal
 */
#[CoversClass(CloneTrait::class)]
class CloneStructTest extends TestCase
{
    public function testClone(): void
    {
        $nestedStruct = new Fixture\CloneStruct();
        $nestedStruct->backedEnum = CloneStructBackedEnum::Case;
        $nestedStruct->unitEnum = CloneStructUnitEnum::Case;

        $original = new Fixture\CloneStruct();
        $original->arrayOfStructs = [$nestedStruct];
        $original->backedEnum = CloneStructBackedEnum::Case;
        $original->nestedStruct = $nestedStruct;
        $original->unitEnum = CloneStructUnitEnum::Case;

        $clone = clone $original;

        static::assertEquals($original, $clone);
        static::assertNotSame($original, $clone);

        static::assertNotSame($original->arrayOfStructs[0], $clone->arrayOfStructs[0]);
        static::assertNotSame($original->nestedStruct, $clone->nestedStruct);
    }
}

/**
 * @internal
 */
enum CloneStructBackedEnum: int
{
    case Case = 1;
}

/**
 * @internal
 */
enum CloneStructUnitEnum
{
    case Case;
}
