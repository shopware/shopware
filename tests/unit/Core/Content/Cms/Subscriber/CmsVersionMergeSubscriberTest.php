<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Subscriber\CmsVersionMergeSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
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
     * @param array<string, mixed> $writes
     * @param array<string, mixed> $expectedWrites
     */
    #[DataProvider('versionMergeEventDataProvider')]
    public function testOnVersionMerge(array $writes, array $expectedWrites): void
    {
        $subscriber = new CmsVersionMergeSubscriber();

        // some cases deliberately deviate from the typed Writes shape
        // @phpstan-ignore argument.type
        $event = new BeforeVersionMergeEvent($writes);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $subscriber->onBeforeVersionMerge($event);
        } finally {
            restore_error_handler();
        }

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

        yield 'Missing delete key leaves writes unchanged' => [
            'writes' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'update' => [],
            ],
        ];

        yield 'Empty cms_block deletion list leaves slots unchanged' => [
            'writes' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => ['cms_block' => []],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => ['cms_block' => []],
                'update' => [],
            ],
        ];

        yield 'Null cms_block deletion leaves slots unchanged' => [
            'writes' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => ['cms_block' => null],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1]]],
                'delete' => ['cms_block' => null],
                'update' => [],
            ],
        ];

        yield 'Block deletion without cms_slot writes leaves writes unchanged' => [
            'writes' => [
                'insert' => [],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => [],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => [],
            ],
        ];

        yield 'Missing insert and update keys leave writes unchanged' => [
            'writes' => [
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
            ],
            'expectedWrites' => [
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
            ],
        ];

        yield 'Empty and null cms_slot writes are left as they are' => [
            'writes' => [
                'insert' => ['cms_slot' => []],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => ['cms_slot' => null],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => []],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => ['cms_slot' => null],
            ],
        ];

        yield 'Slots in update writes referencing deleted blocks are removed' => [
            'writes' => [
                'insert' => [],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                    ['id' => $slotId2, 'blockId' => $blockId2, 'cmsBlockVersionId' => $versionId1],
                ]],
            ],
            'expectedWrites' => [
                'insert' => [],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => ['cms_slot' => [
                    ['id' => $slotId2, 'blockId' => $blockId2, 'cmsBlockVersionId' => $versionId1],
                ]],
            ],
        ];

        yield 'Slots without block reference are kept' => [
            'writes' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1],
                    ['id' => $slotId2, 'blockId' => null, 'cmsBlockVersionId' => null],
                ]],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1],
                    ['id' => $slotId2, 'blockId' => null, 'cmsBlockVersionId' => null],
                ]],
                'delete' => ['cms_block' => [['id' => $blockId1, 'versionId' => $versionId1]]],
                'update' => [],
            ],
        ];

        yield 'Deleted blocks without usable ids remove nothing' => [
            'writes' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => '0', 'cmsBlockVersionId' => '0'],
                    ['id' => $slotId2, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                ]],
                'delete' => ['cms_block' => [['id' => '0', 'versionId' => '0'], ['id' => $blockId1], ['versionId' => $versionId1]]],
                'update' => [],
            ],
            'expectedWrites' => [
                'insert' => ['cms_slot' => [
                    ['id' => $slotId1, 'blockId' => '0', 'cmsBlockVersionId' => '0'],
                    ['id' => $slotId2, 'blockId' => $blockId1, 'cmsBlockVersionId' => $versionId1],
                ]],
                'delete' => ['cms_block' => [['id' => '0', 'versionId' => '0'], ['id' => $blockId1], ['versionId' => $versionId1]]],
                'update' => [],
            ],
        ];
    }
}
