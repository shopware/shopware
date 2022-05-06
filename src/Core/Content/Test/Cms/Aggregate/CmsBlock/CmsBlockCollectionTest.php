<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\Aggregate\CmsBlock;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CmsBlockCollectionTest extends TestCase
{
    public function testGetAllSlotsFromBlocks(): void
    {
        $collection = new CmsBlockCollection();
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());

        static::assertCount(15, $collection->getSlots());
    }

    public function testSetAllSlotsInBlocks(): void
    {
        $collection = new CmsBlockCollection();
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());
        $collection->add($this->getBlock());

        $slots = $collection->getSlots();

        /** @var CmsSlotEntity $slot */
        $slot = $slots->last();
        $slot->setConfig(['overwrite' => true]);

        $collection->setSlots($slots);

        /** @var CmsSlotEntity $lastSlot */
        $lastSlot = $collection->getSlots()->last();

        static::assertEquals(['overwrite' => true], $lastSlot->getConfig());
    }

    private function getBlock(): CmsBlockEntity
    {
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier(Uuid::randomHex());
        $block->setType('block');

        $block->setSlots(new CmsSlotCollection([
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
            $this->getSlot(),
        ]));

        return $block;
    }

    private function getSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setType('slot');
        $slot->setConfig([]);
        $slot->setSlot(uniqid('', true));
        $slot->setUniqueIdentifier(Uuid::randomHex());

        return $slot;
    }
}
