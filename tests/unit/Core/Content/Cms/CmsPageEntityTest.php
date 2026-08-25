<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsPageEntity::class)]
class CmsPageEntityTest extends TestCase
{
    public function testElementLookupsAreEmptyWithoutSections(): void
    {
        $page = new CmsPageEntity();

        static::assertSame([], $page->getElementsOfType('image'));
        static::assertSame([], $page->getAllElements());
    }

    public function testElementsOfTypeReturnsOnlyMatchingSlots(): void
    {
        $image = $this->createSlot('image-slot', 'image');
        $text = $this->createSlot('text-slot', 'text');

        $page = $this->createPageWithSlots($image, $text);

        static::assertSame([$image], $page->getElementsOfType('image'));
    }

    public function testAllElementsReturnsEverySlot(): void
    {
        $image = $this->createSlot('image-slot', 'image');
        $text = $this->createSlot('text-slot', 'text');

        $page = $this->createPageWithSlots($image, $text);

        static::assertSame([$image, $text], $page->getAllElements());
    }

    private function createSlot(string $id, string $type): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setId($id);
        $slot->setType($type);
        $slot->setSlot($id);

        return $slot;
    }

    private function createPageWithSlots(CmsSlotEntity ...$slots): CmsPageEntity
    {
        $block = new CmsBlockEntity();
        $block->setId('block');
        $block->setSlots(new CmsSlotCollection($slots));

        $section = new CmsSectionEntity();
        $section->setId('section');
        $section->setBlocks(new CmsBlockCollection([$block]));

        $page = new CmsPageEntity();
        $page->setSections(new CmsSectionCollection([$section]));

        return $page;
    }
}
