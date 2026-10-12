<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Aggregate\CmsSection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsSectionCollection::class)]
class CmsSectionCollectionTest extends TestCase
{
    public function testGetBlocksMergesAllSectionsAndSkipsSectionsWithoutBlocks(): void
    {
        $block = new CmsBlockEntity();
        $block->setId('block-a');

        $withBlocks = new CmsSectionEntity();
        $withBlocks->setId('section-a');
        $withBlocks->setBlocks(new CmsBlockCollection([$block]));

        $withoutBlocks = new CmsSectionEntity();
        $withoutBlocks->setId('section-b');

        $blocks = (new CmsSectionCollection([$withBlocks, $withoutBlocks]))->getBlocks();

        static::assertCount(1, $blocks);
        static::assertSame($block, $blocks->first());
    }
}
