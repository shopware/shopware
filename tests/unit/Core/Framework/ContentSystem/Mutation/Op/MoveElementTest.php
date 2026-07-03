<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(MoveElement::class)]
class MoveElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('relocates the element and its subtree into the new parent slot and reports the whole moved subtree as affected')]
    public function testMoveRelocatesSubtreeToNewParent(): void
    {
        $tree = [
            new ContentElement('movable', 'Sw:Block', [], [], [
                'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
            ]),
            new ContentElement('target', 'Sw:Block'),
        ];

        $move = new MoveElement('movable', 'target', 'content');
        $result = $move->apply($tree);

        static::assertCount(1, $result);
        static::assertSame('target', $result[0]->getId());
        $moved = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame('movable', $moved[0]->getId());
        static::assertSame('child', array_values($moved[0]->getSlots()['content']->getElements())[0]->getId());
        static::assertSame(['movable', 'child'], $move->affected());
    }

    #[TestDox('treats a same-parent reorder as a pure structural change with empty affected, even under an indexed distribution')]
    public function testReorderWithinSameSlotReportsEmptyAffected(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'Sw:Block'),
                new ContentElement('b', 'Sw:Block'),
            ]),
        ], new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, IndexedDistributionConfig::simple())], []));

        $move = new MoveElement('b', 'parent', 'content', 0);
        $result = $move->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['b', 'a'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
        static::assertSame([], $move->affected());
    }

    #[TestDox('treats a move to a different slot under the same parent as a pure structural change with empty affected')]
    public function testMoveToDifferentSlotSameParentReportsEmptyAffected(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'left' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
            'right' => new SlotContent([]),
        ]);

        $move = new MoveElement('child', 'parent', 'right');
        $result = $move->apply([$parent]);

        $right = array_values($result[0]->getSlots()['right']->getElements());
        static::assertSame('child', $right[0]->getId());
        static::assertSame([], $move->affected());
    }

    #[TestDox('reuses the element current slot for a same-parent move that omits the new slot')]
    public function testMoveSameParentWithoutSlotReusesCurrentSlot(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'Sw:Block'),
                new ContentElement('child', 'Sw:Block'),
            ]),
        ]);

        $move = new MoveElement('child', 'parent', null, 0);
        $result = $move->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['child', 'a'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
        static::assertSame([], $move->affected());
    }

    #[TestDox('carries attributed specifications over to the rebuilt receiving parent')]
    public function testMovePreservesAttributedSpecificationsOnRebuiltParent(): void
    {
        $target = ContentElementBuilder::create('Sw:Block', 'target')
            ->withAttributedSpecification('product', 'spec-1')
            ->build();
        $tree = [new ContentElement('movable', 'Sw:Card'), $target];

        $result = (new MoveElement('movable', 'target', 'content'))->apply($tree);

        static::assertSame('target', $result[0]->getId());
        static::assertSame(['product' => 'spec-1'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('moves a nested element out to the root and reports the moved subtree as affected')]
    public function testMoveToRootDetachesFromParent(): void
    {
        $tree = [
            new ContentElement('parent', 'Sw:Block', [], [], [
                'content' => new SlotContent([new ContentElement('movable', 'Sw:Block')]),
            ]),
        ];

        $move = new MoveElement('movable');
        $result = $move->apply($tree);

        static::assertSame(['parent', 'movable'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
        static::assertSame(['movable'], $move->affected());
    }

    /**
     * @param non-empty-string $newParentId
     */
    #[DataProvider('rejectsCycleTargetProvider')]
    #[TestDox('rejects moving an element onto itself or one of its descendants')]
    public function testMoveOntoSelfOrDescendantRejected(string $newParentId): void
    {
        $tree = [
            new ContentElement('movable', 'Sw:Block', [], [], [
                'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
            ]),
        ];

        $move = new MoveElement('movable', $newParentId, 'content');

        $this->expectExceptionObject(ContentSystemException::mutationCycle('movable'));
        $move->apply($tree);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsCycleTargetProvider(): iterable
    {
        yield 'onto itself' => ['movable'];
        yield 'onto a descendant' => ['child'];
    }

    #[TestDox('rejects moving an element absent from the tree with a 400')]
    public function testMoveMissingElementRejected(): void
    {
        $move = new MoveElement('ghost', 'target', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $move->apply([new ContentElement('target', 'Sw:Block')]);
    }

    #[TestDox('rejects moving into a parent absent from the tree with a 400')]
    public function testMoveToMissingParentRejected(): void
    {
        $move = new MoveElement('movable', 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $move->apply([new ContentElement('movable', 'Sw:Block')]);
    }

    #[TestDox('rejects a cross-parent move without a slot with a 400')]
    public function testMoveToNewParentWithoutSlotRejected(): void
    {
        $tree = [
            new ContentElement('movable', 'Sw:Block'),
            new ContentElement('target', 'Sw:Block'),
        ];

        $move = new MoveElement('movable', 'target');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $move->apply($tree);
    }

    #[TestDox('does not mutate the input tree')]
    public function testMoveDoesNotMutateInput(): void
    {
        $tree = [
            new ContentElement('parent', 'Sw:Block', [], [], [
                'content' => new SlotContent([
                    new ContentElement('movable', 'Sw:Block', [], ['title' => 'Section'], [
                        'inner' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
                    ]),
                ]),
            ]),
        ];
        $before = $this->snapshotTree($tree);

        (new MoveElement('movable'))->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }
}
