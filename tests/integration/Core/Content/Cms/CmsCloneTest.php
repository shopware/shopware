<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the clone payloads of the Administration's duplicate actions in sw-cms-list and sw-cms-detail.
 *
 * @internal
 */
#[Package('discovery')]
class CmsCloneTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const SLOT_CONFIG = ['content' => ['source' => 'static', 'value' => 'Lorem ipsum']];

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $pageRepository;

    protected function setUp(): void
    {
        $this->pageRepository = static::getContainer()->get('cms_page.repository');
    }

    public function testDuplicateLayoutCopiesSectionsBlocksAndSlots(): void
    {
        $ids = $this->createPage();

        $cloneId = $this->clone('cms-page', $ids['page'], [
            'overwrites' => ['name' => 'Layout - Copy'],
        ]);

        $clone = $this->loadPage($cloneId);
        static::assertSame('Layout - Copy', $clone->getName());
        static::assertSame('landingpage', $clone->getType());

        $sections = $clone->getSections();
        static::assertInstanceOf(CmsSectionCollection::class, $sections);
        static::assertCount(1, $sections);
        $this->assertCopiedSection($ids, $sections->first());

        static::assertCount(1, $this->loadPage($ids['page'])->getSections() ?? []);
    }

    public function testDuplicateLockedLayoutCreatesEditableCopy(): void
    {
        $ids = $this->createPage();
        static::getContainer()->get(Connection::class)->executeStatement(
            <<<'SQL'
                UPDATE cms_page SET locked = 1 WHERE id = :id
            SQL,
            ['id' => Uuid::fromHexToBytes($ids['page'])]
        );

        $cloneId = $this->clone('cms-page', $ids['page'], [
            'overwrites' => ['name' => 'Layout - Copy'],
        ]);

        $clone = $this->loadPage($cloneId);
        static::assertFalse($clone->getLocked());
        static::assertCount(1, $clone->getSections() ?? []);
        static::assertTrue($this->loadPage($ids['page'])->getLocked());
    }

    public function testDuplicateLayoutLeavesAssignmentsWithSource(): void
    {
        $ids = $this->createPage();
        $categoryId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        static::getContainer()->get('category.repository')->create([[
            'id' => $categoryId,
            'name' => 'Category using the layout',
            'cmsPageId' => $ids['page'],
        ]], $context);
        static::getContainer()->get('landing_page.repository')->create([[
            'id' => $landingPageId,
            'name' => 'Landing page using the layout',
            'url' => 'landing-page-using-the-layout',
            'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
            'cmsPageId' => $ids['page'],
        ]], $context);

        $cloneId = $this->clone('cms-page', $ids['page'], [
            'overwrites' => ['name' => 'Layout - Copy'],
        ]);

        $clone = $this->loadPage($cloneId);
        static::assertCount(0, $clone->getCategories() ?? []);
        static::assertCount(0, $clone->getLandingPages() ?? []);

        $source = $this->loadPage($ids['page']);
        static::assertSame([$categoryId], array_values($source->getCategories()?->getIds() ?? []));
        static::assertSame([$landingPageId], array_values($source->getLandingPages()?->getIds() ?? []));
    }

    public function testDuplicateSectionInsertsCopyWithBlocksAfterSource(): void
    {
        $ids = $this->createPage();

        $cloneId = $this->clone('cms-section', $ids['section'], [
            'overwrites' => ['position' => 1],
            'cloneChildren' => true,
        ]);

        $sections = $this->loadPage($ids['page'])->getSections();
        static::assertInstanceOf(CmsSectionCollection::class, $sections);
        static::assertSame([$ids['section'], $cloneId], array_values($sections->getIds()));
        static::assertSame(0, $sections->get($ids['section'])?->getPosition());

        $clonedSection = $sections->get($cloneId);
        static::assertInstanceOf(CmsSectionEntity::class, $clonedSection);
        static::assertSame(1, $clonedSection->getPosition());
        $this->assertCopiedSection($ids, $clonedSection);
    }

    public function testDuplicateBlockInsertsCopyWithSlotsAfterSource(): void
    {
        $ids = $this->createPage();

        $cloneId = $this->clone('cms-block', $ids['block'], [
            'overwrites' => ['position' => 1],
            'cloneChildren' => true,
        ]);

        $blocks = $this->loadPage($ids['page'])->getSections()?->first()?->getBlocks();
        static::assertInstanceOf(CmsBlockCollection::class, $blocks);
        static::assertSame([$ids['block'], $cloneId], array_values($blocks->getIds()));
        static::assertSame(0, $blocks->get($ids['block'])?->getPosition());

        $clonedBlock = $blocks->get($cloneId);
        static::assertInstanceOf(CmsBlockEntity::class, $clonedBlock);
        static::assertSame(1, $clonedBlock->getPosition());
        static::assertSame($ids['section'], $clonedBlock->getSectionId());
        $this->assertCopiedBlock($ids, $clonedBlock);
    }

    /**
     * @param array{page: string, section: string, block: string, slot: string} $source
     */
    private function assertCopiedSection(array $source, ?CmsSectionEntity $section): void
    {
        static::assertInstanceOf(CmsSectionEntity::class, $section);
        static::assertNotSame($source['section'], $section->getId());
        static::assertSame('Hero', $section->getName());
        static::assertSame('full_width', $section->getSizingMode());
        static::assertSame('#000000', $section->getBackgroundColor());
        static::assertSame('hero', $section->getCssClass());

        $blocks = $section->getBlocks();
        static::assertInstanceOf(CmsBlockCollection::class, $blocks);
        static::assertCount(1, $blocks);
        $this->assertCopiedBlock($source, $blocks->first());
    }

    /**
     * @param array{page: string, section: string, block: string, slot: string} $source
     */
    private function assertCopiedBlock(array $source, ?CmsBlockEntity $block): void
    {
        static::assertInstanceOf(CmsBlockEntity::class, $block);
        static::assertNotSame($source['block'], $block->getId());
        static::assertSame('text', $block->getType());
        static::assertSame('Intro', $block->getName());
        static::assertSame('main', $block->getSectionPosition());
        static::assertSame('20px', $block->getMarginTop());
        static::assertSame('intro', $block->getCssClass());

        $slots = $block->getSlots();
        static::assertInstanceOf(CmsSlotCollection::class, $slots);
        static::assertCount(1, $slots);

        $slot = $slots->first();
        static::assertInstanceOf(CmsSlotEntity::class, $slot);
        static::assertNotSame($source['slot'], $slot->getId());
        static::assertSame('text', $slot->getType());
        static::assertSame('content', $slot->getSlot());
        static::assertSame(self::SLOT_CONFIG, $slot->getConfig());
    }

    /**
     * @return array{page: string, section: string, block: string, slot: string}
     */
    private function createPage(): array
    {
        $ids = [
            'page' => Uuid::randomHex(),
            'section' => Uuid::randomHex(),
            'block' => Uuid::randomHex(),
            'slot' => Uuid::randomHex(),
        ];

        $this->pageRepository->create([[
            'id' => $ids['page'],
            'name' => 'Layout',
            'type' => 'landingpage',
            'sections' => [[
                'id' => $ids['section'],
                'type' => 'default',
                'position' => 0,
                'name' => 'Hero',
                'sizingMode' => 'full_width',
                'backgroundColor' => '#000000',
                'cssClass' => 'hero',
                'blocks' => [[
                    'id' => $ids['block'],
                    'type' => 'text',
                    'position' => 0,
                    'name' => 'Intro',
                    'sectionPosition' => 'main',
                    'marginTop' => '20px',
                    'cssClass' => 'intro',
                    'slots' => [[
                        'id' => $ids['slot'],
                        'type' => 'text',
                        'slot' => 'content',
                        'config' => self::SLOT_CONFIG,
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());

        return $ids;
    }

    /**
     * @param array<string, mixed> $behavior
     */
    private function clone(string $entity, string $id, array $behavior): string
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/clone/' . $entity . '/' . $id, $behavior);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertIsString($body['id']);

        return $body['id'];
    }

    private function loadPage(string $pageId): CmsPageEntity
    {
        $criteria = new Criteria([$pageId]);
        $criteria->addAssociations(['categories', 'landingPages']);
        $criteria->getAssociation('sections')
            ->addSorting(new FieldSorting('position'))
            ->getAssociation('blocks')
            ->addSorting(new FieldSorting('position'))
            ->addAssociation('slots');

        $page = $this->pageRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(CmsPageEntity::class, $page);

        return $page;
    }
}
