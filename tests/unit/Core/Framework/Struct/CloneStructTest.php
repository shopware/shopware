<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Tests\Unit\Core\Framework\Struct\Fixture\CloneStruct;

/**
 * @internal
 */
#[CoversClass(CloneTrait::class)]
class CloneStructTest extends TestCase
{
    public function testClone(): void
    {
        $nestedStruct = new CloneStruct();
        $nestedStruct->backedEnum = Fixture\CloneStructBackedEnum::Case;
        $nestedStruct->unitEnum = Fixture\CloneStructUnitEnum::Case;

        $original = new CloneStruct();
        $original->arrayOfStructs = [$nestedStruct];
        $original->backedEnum = Fixture\CloneStructBackedEnum::Case;
        $original->nestedStruct = $nestedStruct;
        $original->unitEnum = Fixture\CloneStructUnitEnum::Case;

        $clone = clone $original;

        static::assertEquals($original, $clone);
        static::assertNotSame($original, $clone);

        static::assertNotSame($original->arrayOfStructs[0], $clone->arrayOfStructs[0]);
        static::assertNotSame($original->nestedStruct, $clone->nestedStruct);
    }
}
