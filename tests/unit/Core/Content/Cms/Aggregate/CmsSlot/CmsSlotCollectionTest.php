<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Aggregate\CmsSlot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CmsSlotCollection::class)]
class CmsSlotCollectionTest extends TestCase
{
    public function testGetSlot(): void
    {
        $collection = new CmsSlotCollection([
            $this->getSlot('left'),
            $this->getSlot('right'),
            $this->getSlot('top'),
            $this->getSlot('bottom'),
        ]);

        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('left'));
        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('right'));
        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('top'));
        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('bottom'));

        static::assertEquals('left', $collection->getSlot('left')->getSlot());
        static::assertEquals('right', $collection->getSlot('right')->getSlot());
        static::assertEquals('top', $collection->getSlot('top')->getSlot());
        static::assertEquals('bottom', $collection->getSlot('bottom')->getSlot());
    }

    public function testGetSlotAfterAdding(): void
    {
        $leftSlot = $this->getSlot('left');

        $collection = new CmsSlotCollection([
            $this->getSlot('top'),
            $this->getSlot('bottom'),
        ]);

        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('top'));
        static::assertInstanceOf(CmsSlotEntity::class, $collection->getSlot('bottom'));

        static::assertEquals('top', $collection->getSlot('top')->getSlot());
        static::assertEquals('bottom', $collection->getSlot('bottom')->getSlot());
        static::assertNull($collection->getSlot('left'));

        $collection->add($leftSlot);

        static::assertNotNull($collection->getSlot('left'));
        static::assertEquals('left', $collection->getSlot('left')->getSlot());
    }

    private function getSlot(string $slotName): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier(Uuid::randomHex());
        $slot->setSlot($slotName);

        return $slot;
    }
}
