<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneIdMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CloneIdMap::class)]
class CloneIdMapTest extends TestCase
{
    public function testASourceRowGetsOneCloneId(): void
    {
        $map = new CloneIdMap();
        $sourceId = Uuid::randomHex();

        $cloneId = $map->cloneIdFor('order_address', $sourceId);

        static::assertTrue(Uuid::isValid($cloneId));
        static::assertNotSame($sourceId, $cloneId);
        static::assertSame($cloneId, $map->cloneIdFor('order_address', $sourceId));
        static::assertSame($cloneId, $map->get('order_address', $sourceId));
        static::assertFalse($map->isEmpty());
    }

    public function testRowsAreKeyedByEntity(): void
    {
        $map = new CloneIdMap();
        $sourceId = Uuid::randomHex();

        static::assertNotSame($map->cloneIdFor('order_address', $sourceId), $map->cloneIdFor('order_delivery', $sourceId));
    }

    public function testARowThatWasNotClonedHasNoId(): void
    {
        $map = new CloneIdMap();

        static::assertTrue($map->isEmpty());
        static::assertNull($map->get('order_address', Uuid::randomHex()));
    }
}
