<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Subscriber\CmsVersionMergeSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-import-type Writes from BeforeVersionMergeEvent
 *
 * @internal
 */
#[CoversClass(CmsVersionMergeSubscriber::class)]
class CmsVersionMergeSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $expectedEvents = [
            BeforeVersionMergeEvent::class => 'onBeforeVersionMerge',
        ];

        static::assertSame($expectedEvents, CmsVersionMergeSubscriber::getSubscribedEvents());
    }

    /**
     * @param Writes $writes
     * @param Writes $expectedWrites
     */
    #[DataProvider('versionMergeEventDataProvider')]
    public function testOnVersionMerge(array $writes, array $expectedWrites): void
    {
        $subscriber = new CmsVersionMergeSubscriber();

        $event = new BeforeVersionMergeEvent($writes);

        $subscriber->onBeforeVersionMerge($event);

        static::assertSame($expectedWrites, $event->writes);
    }

    public static function versionMergeEventDataProvider(): \Generator
    {
        $blockId1 = Uuid::randomHex();
        $blockId2 = Uuid::randomHex();
        $slotId1 = Uuid::randomHex();
        $slotId2 = Uuid::randomHex();
        $versionId1 = Uuid::randomHex();
        $versionId2 = Uuid::randomHex();

        yield 'No cms_block deletions, writes remain unchanged' => [
            'writes' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => [],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => [],
                'update' => [],
            ],
        ];

        yield 'Slots referencing deleted blocks are removed' => [
            'writes' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                    ['id' => $slotId2, 'blockId' => $blockId2, 'cmsBlockVersionId' => $versionId2],
                ]],
                'delete' => ['cms_block' => [
                    ['id' => $blockId1, 'versionId' => $versionId1],
                ]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId2, 'blockId' => $blockId2, 'cmsBlockVersionId' => $versionId2],
                ]],
                'delete' => ['cms_block' => [
                    ['id' => $blockId1, 'versionId' => $versionId1],
                ]],
                'update' => [],
            ],
        ];

        yield 'Slots remain if block deletion does not match version' => [
            'writes' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                ]],
                'delete' => ['cms_block' => [
                    ['id' => $blockId1, 'versionId' => $versionId2],
                ]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                ]],
                'delete' => ['cms_block' => [
                    ['id' => $blockId1, 'versionId' => $versionId2],
                ]],
                'update' => [],
            ],
        ];
    }
}
