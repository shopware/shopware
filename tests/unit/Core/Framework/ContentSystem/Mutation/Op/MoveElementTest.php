<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MoveElement::class)]
class MoveElementTest extends TestCase
{
    #[TestDox('relocates the element and its subtree into the new parent slot and reports the whole moved subtree as affected')]
    public function testMoveRelocatesSubtreeToNewParent(): void
    {
        $tree = new StoredTree([
            new StoredElement('movable', 'Sw:Block', [], [], [
                'content' => [new StoredElement('child', 'Sw:Block')],
            ]),
            new StoredElement('target', 'Sw:Block'),
        ]);

        $move = new MoveElement('movable', 'target', 'content');
        $result = $move->apply($tree);

        static::assertCount(1, $result->roots);
        static::assertSame('target', $result->roots[0]->id);
        $moved = $result->roots[0]->slots['content'];
        static::assertSame('movable', $moved[0]->id);
        static::assertSame('child', $moved[0]->slots['content'][0]->id);
        static::assertSame(['movable', 'child'], $move->affected());
    }

    #[TestDox('treats a same-parent reorder as a pure structural change with empty affected, even under an indexed distribution')]
    public function testReorderWithinSameSlotReportsEmptyAffected(): void
    {
        $parent = new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [
                new StoredElement('a', 'Sw:Block'),
                new StoredElement('b', 'Sw:Block'),
            ],
        ], new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, IndexedDistributionConfig::simple())], []));

        $move = new MoveElement('b', 'parent', 'content', 0);
        $result = $move->apply(new StoredTree([$parent]));

        static::assertSame(['b', 'a'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
        static::assertSame([], $move->affected());
    }

    #[TestDox('treats a move to a different slot under the same parent as a pure structural change with empty affected')]
    public function testMoveToDifferentSlotSameParentReportsEmptyAffected(): void
    {
        $parent = new StoredElement('parent', 'Sw:Block', [], [], [
            'left' => [new StoredElement('child', 'Sw:Block')],
            'right' => [],
        ]);

        $move = new MoveElement('child', 'parent', 'right');
        $result = $move->apply(new StoredTree([$parent]));

        static::assertSame('child', $result->roots[0]->slots['right'][0]->id);
        static::assertSame([], $move->affected());
    }

    #[TestDox('reuses the element current slot for a same-parent move that omits the new slot')]
    public function testMoveSameParentWithoutSlotReusesCurrentSlot(): void
    {
        $parent = new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [
                new StoredElement('a', 'Sw:Block'),
                new StoredElement('child', 'Sw:Block'),
            ],
        ]);

        $move = new MoveElement('child', 'parent', null, 0);
        $result = $move->apply(new StoredTree([$parent]));

        static::assertSame(['child', 'a'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
        static::assertSame([], $move->affected());
    }

    #[TestDox('carries attributed specifications over to the rebuilt receiving parent')]
    public function testMovePreservesAttributedSpecificationsOnRebuiltParent(): void
    {
        $target = StoredElementBuilder::create('Sw:Block', 'target')
            ->withAttributedSpecification('product', 'spec-1')
            ->build();
        $tree = new StoredTree([new StoredElement('movable', 'Sw:Card'), $target]);

        $result = (new MoveElement('movable', 'target', 'content'))->apply($tree);

        static::assertSame('target', $result->roots[0]->id);
        static::assertSame(['product' => 'spec-1'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('moves a nested element out to the root and reports the moved subtree as affected')]
    public function testMoveToRootDetachesFromParent(): void
    {
        $tree = new StoredTree([
            new StoredElement('parent', 'Sw:Block', [], [], [
                'content' => [new StoredElement('movable', 'Sw:Block')],
            ]),
        ]);

        $move = new MoveElement('movable');
        $result = $move->apply($tree);

        static::assertSame(['parent', 'movable'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
        static::assertSame(['movable'], $move->affected());
    }

    /**
     * @param non-empty-string $newParentId
     */
    #[DataProvider('rejectsCycleTargetProvider')]
    #[TestDox('rejects moving an element onto itself or one of its descendants')]
    public function testMoveOntoSelfOrDescendantRejected(string $newParentId): void
    {
        $tree = new StoredTree([
            new StoredElement('movable', 'Sw:Block', [], [], [
                'content' => [new StoredElement('child', 'Sw:Block')],
            ]),
        ]);

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
        $move->apply(new StoredTree([new StoredElement('target', 'Sw:Block')]));
    }

    #[TestDox('rejects moving into a parent absent from the tree with a 400')]
    public function testMoveToMissingParentRejected(): void
    {
        $move = new MoveElement('movable', 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $move->apply(new StoredTree([new StoredElement('movable', 'Sw:Block')]));
    }

    #[TestDox('rejects a cross-parent move without a slot with a 400')]
    public function testMoveToNewParentWithoutSlotRejected(): void
    {
        $tree = new StoredTree([
            new StoredElement('movable', 'Sw:Block'),
            new StoredElement('target', 'Sw:Block'),
        ]);

        $move = new MoveElement('movable', 'target');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $move->apply($tree);
    }
}
