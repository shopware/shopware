<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SalesChannelCmsPageLoader::class)]
class SalesChannelCmsPageLoaderTest extends TestCase
{
    public function testLoadCmsPagesInRightOrder(): void
    {
        $cmsPages = $this->getCmsPagesWithTestData();

        /** @var StaticEntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = new StaticEntityRepository([$cmsPages], new CmsPageDefinition());

        $loader = new SalesChannelCmsPageLoader(
            $cmsPageRepository,
            $this->createMock(CmsSlotsDataResolver::class),
            $this->createMock(EventDispatcher::class)
        );

        $result = $loader->load(new Request(), new Criteria(), Generator::createSalesChannelContext());

        $elements = $result->getElements();
        static::assertContainsOnlyInstancesOf(CmsPageEntity::class, $elements);

        $this->assertCmsPage1($elements['page-1']);

        // empty cms page without sections
        static::assertEmpty($elements['page-2']->getSections());
    }

    public function testLoadWithOverriddenConfig(): void
    {
        $cmsPages = $this->getCmsPagesWithTestData();
        $cmsPages->remove('page-2');

        /** @var StaticEntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = new StaticEntityRepository([$cmsPages], new CmsPageDefinition());

        $loader = new SalesChannelCmsPageLoader(
            $cmsPageRepository,
            $this->createMock(CmsSlotsDataResolver::class),
            $this->createMock(EventDispatcher::class)
        );

        $config = [
            'page-1' => [
                'slot-1' => [
                    'translated' => 'expected-config',
                ],
            ],
        ];

        $result = $loader->load(
            new Request(),
            new Criteria(),
            Generator::createSalesChannelContext(),
            $config
        );

        $page = $result->getElements()['page-1'];
        static::assertInstanceOf(CmsPageEntity::class, $page);

        $section = $page->getSections()?->get('section-1');
        static::assertInstanceOf(CmsSectionEntity::class, $section);

        $block = $section->getBlocks()?->get('block-2');
        static::assertInstanceOf(CmsBlockEntity::class, $block);

        $slot = $block->getSlots()?->get('slot-1');
        static::assertInstanceOf(CmsSlotEntity::class, $slot);

        $config = $slot->getConfig();
        static::assertIsArray($config);
        static::assertSame('expected-config', $config['translated']);
    }

    private function assertCmsPage1(CmsPageEntity $cmsPage): void
    {
        $cmsPage = json_decode((string) json_encode($cmsPage), true);

        $sections = $cmsPage['sections'];
        static::assertCount(2, $cmsPage['sections']);

        $currentSection = array_shift($sections);
        static::assertSame('section-2', $currentSection['id']);
        static::assertSame(1, $currentSection['position']);
        static::assertEmpty($currentSection['blocks']);

        $currentSection = array_shift($sections);
        static::assertSame('section-1', $currentSection['id']);
        static::assertSame(2, $currentSection['position']);

        $blocks = $currentSection['blocks'];
        static::assertCount(3, $blocks);

        $currentBlock = array_shift($blocks);
        static::assertSame('block-2', $currentBlock['id']);
        static::assertSame(1, $currentBlock['position']);

        $slots = $currentBlock['slots'];
        static::assertCount(1, $slots);
        static::assertSame('content', $slots[0]['slot']);

        $currentBlock = array_shift($blocks);
        static::assertSame('block-3', $currentBlock['id']);
        static::assertSame(2, $currentBlock['position']);
        static::assertEmpty($currentBlock['slots']);

        $currentBlock = array_shift($blocks);
        static::assertSame('block-1', $currentBlock['id']);
        static::assertSame(3, $currentBlock['position']);

        $slots = $currentBlock['slots'];
        static::assertCount(3, $slots);
        static::assertSame('content', $slots[0]['slot']);
        static::assertSame('left', $slots[1]['slot']);
        static::assertSame('right', $slots[2]['slot']);
    }

    private function getCmsPagesWithTestData(): CmsPageCollection
    {
        $cmsPage1 = (new CmsPageEntity())->assign([
            'id' => 'page-1',
            'sections' => new CmsSectionCollection([
                (new CmsSectionEntity())->assign([
                    'id' => 'section-1',
                    'position' => 2,
                    'blocks' => new CmsBlockCollection([
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-1',
                            'position' => 3,
                            'slots' => new CmsSlotCollection([
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-1',
                                    'slot' => 'left',
                                    'config' => ['translated' => '1'],
                                    'translated' => [
                                        'config' => ['Config'],
                                    ],
                                ]),
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-2',
                                    'slot' => 'right',
                                    'translated' => [
                                        'config' => ['Config'],
                                    ],
                                ]),
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-3',
                                    'slot' => 'content',
                                ]),
                            ]),
                        ]),
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-2',
                            'position' => 1,
                            'slots' => new CmsSlotCollection([
                                (new CmsSlotEntity())->assign([
                                    'id' => 'slot-1',
                                    'slot' => 'content',
                                    'config' => ['translated' => '0'],
                                ]),
                            ]),
                        ]),
                        (new CmsBlockEntity())->assign([
                            'id' => 'block-3',
                            'position' => 2,
                        ]),
                    ]),
                ]),
                (new CmsSectionEntity())->assign([
                    'id' => 'section-2',
                    'position' => 1,
                ]),
            ]),
        ]);

        $cmsPage2 = (new CmsPageEntity())->assign([
            'id' => 'page-2',
        ]);

        return new CmsPageCollection([$cmsPage1, $cmsPage2]);
    }
}
