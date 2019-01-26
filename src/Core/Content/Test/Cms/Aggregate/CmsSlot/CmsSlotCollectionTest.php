<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\Aggregate\CmsSlot;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Struct\Uuid;

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

        $this->assertEquals('left', $collection->getSlot('left')->getSlot());
        $this->assertEquals('right', $collection->getSlot('right')->getSlot());
        $this->assertEquals('top', $collection->getSlot('top')->getSlot());
        $this->assertEquals('bottom', $collection->getSlot('bottom')->getSlot());
    }

    public function testGetSlotAfterAdding(): void
    {
        $leftSlot = $this->getSlot('left');

        $collection = new CmsSlotCollection([
            $this->getSlot('top'),
            $this->getSlot('bottom'),
        ]);

        $this->assertEquals('top', $collection->getSlot('top')->getSlot());
        $this->assertEquals('bottom', $collection->getSlot('bottom')->getSlot());
        $this->assertNull($collection->getSlot('left'));

        $collection->add($leftSlot);

        $this->assertNotNull($collection->getSlot('left'));
        $this->assertEquals('left', $collection->getSlot('left')->getSlot());
    }

    private function getSlot(string $slotName): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier(Uuid::uuid4()->getHex());
        $slot->setSlot($slotName);

        return $slot;
    }
}
