<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTree::class)]
class StoredTreeTest extends TestCase
{
    #[TestDox('find returns a root element by id')]
    public function testFindReturnsARootElement(): void
    {
        $tree = $this->tree();

        static::assertSame('root-2', $tree->find('root-2')?->id);
    }

    #[TestDox('find reaches an element nested below a slot')]
    public function testFindReachesANestedElement(): void
    {
        $tree = $this->tree();

        static::assertSame('grandchild-1', $tree->find('grandchild-1')?->id);
    }

    #[TestDox('find returns null for an id the forest does not carry')]
    public function testFindReturnsNullForAnUnknownId(): void
    {
        static::assertNull($this->tree()->find('absent'));
    }

    #[TestDox('locate reports a root element with its index and no parent')]
    public function testLocateReportsARootElementWithoutAParent(): void
    {
        $location = $this->tree()->locate('root-2');

        static::assertNotNull($location);
        static::assertSame('root-2', $location['element']->id);
        static::assertSame(1, $location['index']);
        static::assertNull($location['parentId']);
        static::assertNull($location['slot']);
    }

    #[TestDox('locate reports a nested element with its parent, slot and sibling index')]
    public function testLocateReportsANestedElementWithItsParentSlotAndIndex(): void
    {
        $location = $this->tree()->locate('child-b');

        static::assertNotNull($location);
        static::assertSame('child-b', $location['element']->id);
        static::assertSame(1, $location['index']);
        static::assertSame('root-1', $location['parentId']);
        static::assertSame('main', $location['slot']);
    }

    #[TestDox('locate returns null for an id the forest does not carry')]
    public function testLocateReturnsNullForAnUnknownId(): void
    {
        static::assertNull($this->tree()->locate('absent'));
    }

    #[TestDox('locate reports an element nested two levels down with its parent, slot and sibling index')]
    public function testLocateReportsADeeplyNestedElement(): void
    {
        // 'grandchild-1' is not a direct child of a root, so this only resolves through locateUnder()'s second,
        // recursive loop at StoredTree.php:180-188; deleting that loop would leave this locate() call returning
        // null even though the fixture carries the id two levels down.
        $location = $this->tree()->locate('grandchild-1');

        static::assertNotNull($location);
        static::assertSame('grandchild-1', $location['element']->id);
        static::assertSame(0, $location['index']);
        static::assertSame('child-a', $location['parentId']);
        static::assertSame('inner', $location['slot']);
    }

    #[TestDox('ids lists every element in the forest depth first')]
    public function testIdsListsEveryElementDepthFirst(): void
    {
        static::assertSame(
            ['root-1', 'child-a', 'grandchild-1', 'child-b', 'root-2'],
            $this->tree()->ids()
        );
    }

    #[TestDox('remove drops a nested element together with its subtree')]
    public function testRemoveDropsANestedSubtree(): void
    {
        $pruned = $this->tree()->remove('child-a');

        static::assertSame(['root-1', 'child-b', 'root-2'], $pruned->ids());
    }

    #[TestDox('remove drops a root element without touching its siblings')]
    public function testRemoveDropsARootElement(): void
    {
        $pruned = $this->tree()->remove('root-1');

        static::assertSame(['root-2'], $pruned->ids());
    }

    #[TestDox('remove leaves the tree it was called on unchanged')]
    public function testRemoveLeavesTheOriginalTreeUnchanged(): void
    {
        $tree = $this->tree();

        $tree->remove('child-a');

        static::assertSame(['root-1', 'child-a', 'grandchild-1', 'child-b', 'root-2'], $tree->ids());
    }

    #[TestDox('remove returns a structurally unchanged forest for an id the forest does not carry')]
    public function testRemoveIsANoOpForAnUnknownId(): void
    {
        $tree = $this->tree();

        static::assertSame($this->serialize($tree), $this->serialize($tree->remove('absent')));
    }

    #[TestDox('insertAtRoot appends when no index is given')]
    public function testInsertAtRootAppendsWithoutAnIndex(): void
    {
        $inserted = $this->tree()->insertAtRoot(null, [$this->element('root-3')]);

        static::assertSame(['root-1', 'root-2', 'root-3'], $this->rootIds($inserted));
    }

    #[TestDox('insertAtRoot places the nodes at the given index')]
    public function testInsertAtRootPlacesNodesAtTheGivenIndex(): void
    {
        $inserted = $this->tree()->insertAtRoot(0, [$this->element('root-0')]);

        static::assertSame(['root-0', 'root-1', 'root-2'], $this->rootIds($inserted));
    }

    #[TestDox('insertAtRoot appends when the index is beyond the end of the root list')]
    public function testInsertAtRootAppendsWhenTheIndexIsOutOfRange(): void
    {
        $inserted = $this->tree()->insertAtRoot(99, [$this->element('root-3')]);

        static::assertSame(['root-1', 'root-2', 'root-3'], $this->rootIds($inserted));
    }

    #[TestDox('insertAtRoot appends when the index is negative')]
    public function testInsertAtRootAppendsWhenTheIndexIsNegative(): void
    {
        // splice()'s compound guard at StoredTree.php:306 ORs in `$index < 0`; every other insertAtRoot test uses
        // null or an out-of-range positive index, so this operand alone discriminates it. Deleting it would send a
        // negative index down the array_slice branch instead, inserting before the last element rather than
        // appending.
        $inserted = $this->tree()->insertAtRoot(-1, [$this->element('root-3')]);

        static::assertSame(['root-1', 'root-2', 'root-3'], $this->rootIds($inserted));
    }

    #[TestDox('insertIntoSlot places the nodes inside a slot of a nested parent')]
    public function testInsertIntoSlotPlacesNodesUnderANestedParent(): void
    {
        // Every other insertIntoSlot test targets a root-level parent ('root-1', 'root-2'), so the recursive
        // descent in insertInto() at StoredTree.php:254 never runs against a matching subtree. Targeting 'child-a',
        // which is nested under root-1, forces that recursive branch; replacing it with a plain pass-through would
        // leave 'child-a' unmodified and this insert would silently not happen.
        $inserted = $this->tree()->insertIntoSlot('child-a', 'inner', null, [$this->element('grandchild-2')]);

        static::assertSame(
            ['root-1', 'child-a', 'grandchild-1', 'grandchild-2', 'child-b', 'root-2'],
            $inserted->ids()
        );
    }

    #[TestDox('insertIntoSlot places the nodes inside an existing slot at the given index')]
    public function testInsertIntoSlotPlacesNodesInAnExistingSlot(): void
    {
        $inserted = $this->tree()->insertIntoSlot('root-1', 'main', 1, [$this->element('child-new')]);

        static::assertSame(
            ['root-1', 'child-a', 'grandchild-1', 'child-new', 'child-b', 'root-2'],
            $inserted->ids()
        );
    }

    #[TestDox('insertIntoSlot creates a slot the parent does not carry yet')]
    public function testInsertIntoSlotCreatesAMissingSlot(): void
    {
        $inserted = $this->tree()->insertIntoSlot('root-2', 'aside', null, [$this->element('child-new')]);

        $parent = $inserted->find('root-2');

        static::assertNotNull($parent);
        static::assertSame(['aside'], array_keys($parent->slots));
        static::assertSame(['child-new'], array_map(
            static fn (StoredElement $child): string => $child->id,
            $parent->slots['aside']
        ));
    }

    #[TestDox('insertIntoSlot leaves a sibling language-map property value unchanged while rebuilding the slot')]
    public function testInsertIntoSlotLeavesASiblingLanguageMapUnchanged(): void
    {
        $german = Uuid::randomHex();
        $translations = [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', $german => 'Herbstschlussverkauf'];
        $sibling = StoredElementBuilder::create('core:text', 'child-a')->withProperty('text', $translations)->build();
        $tree = new StoredTree([StoredElementBuilder::create('core:section', 'root-1')->withSlot('main', [$sibling])->build()]);

        $inserted = $tree->insertIntoSlot('root-1', 'main', null, [$this->element('child-new')]);

        $carried = $inserted->find('child-a')?->property('text');
        static::assertNotNull($carried);
        static::assertTrue($carried->equals(StoredValue::fromDecoded($translations)));
    }

    #[TestDox('insertIntoSlot returns a structurally unchanged forest for a parent id the forest does not carry')]
    public function testInsertIntoSlotIsANoOpForAnUnknownParentId(): void
    {
        $tree = $this->tree();

        $inserted = $tree->insertIntoSlot('absent', 'main', null, [$this->element('child-new')]);

        static::assertSame($this->serialize($tree), $this->serialize($inserted));
    }

    #[TestDox('replace swaps a nested element for the supplied one')]
    public function testReplaceSwapsANestedElement(): void
    {
        $replaced = $this->tree()->replace('child-a', $this->element('child-z'));

        static::assertSame(['root-1', 'child-z', 'child-b', 'root-2'], $replaced->ids());
    }

    #[TestDox('replace swaps a root element for the supplied one')]
    public function testReplaceSwapsARootElement(): void
    {
        $replaced = $this->tree()->replace('root-2', $this->element('root-z'));

        static::assertSame(['root-1', 'child-a', 'grandchild-1', 'child-b', 'root-z'], $replaced->ids());
    }

    #[TestDox('replace returns a structurally unchanged forest for an id the forest does not carry')]
    public function testReplaceIsANoOpForAnUnknownId(): void
    {
        $tree = $this->tree();

        static::assertSame($this->serialize($tree), $this->serialize($tree->replace('absent', $this->element('replacement'))));
    }

    #[TestDox('replace preserves an untargeted root by value')]
    public function testReplacePreservesAnUntargetedRootByValue(): void
    {
        $untargeted = $this->element('root-2');
        $tree = new StoredTree([$this->element('root-1'), $untargeted]);

        $replaced = $tree->replace('root-1', $this->element('root-z'));

        static::assertEquals($untargeted, $replaced->roots[1]);
    }

    #[TestDox('validate reports nothing for a forest whose ids are all unique')]
    public function testValidateReportsNothingForAWellFormedForest(): void
    {
        static::assertSame([], $this->tree()->validate());
    }

    #[TestDox('validate reports an id reused across two roots')]
    public function testValidateReportsAnIdReusedAcrossRoots(): void
    {
        $tree = new StoredTree([$this->element('root-1'), $this->element('root-1')]);

        $violations = $tree->validate();

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::DuplicateElementId, $violations[0]->code);
        static::assertSame('root-1', $violations[0]->elementId);
        static::assertSame('Element id "root-1" is not unique across the layout.', $violations[0]->message);
    }

    #[TestDox('validate reports an id reused at a different nesting depth')]
    public function testValidateReportsAnIdReusedAcrossNestingDepths(): void
    {
        $deep = StoredElementBuilder::create('core:section', 'root-1')
            ->withSlot('main', [
                StoredElementBuilder::create('core:section', 'child-a')
                    ->withSlot('inner', [$this->element('root-1')])
                    ->build(),
            ])
            ->build();

        $violations = (new StoredTree([$deep]))->validate();

        static::assertCount(1, $violations);
        static::assertSame('root-1', $violations[0]->elementId);
    }

    #[TestDox('validate reports one violation per duplicated id, not one per occurrence')]
    public function testValidateReportsOneViolationPerDuplicatedId(): void
    {
        $tree = new StoredTree([
            $this->element('root-1'),
            $this->element('root-1'),
            $this->element('root-1'),
        ]);

        static::assertCount(1, $tree->validate());
    }

    private function tree(): StoredTree
    {
        $childA = StoredElementBuilder::create('core:section', 'child-a')
            ->withSlot('inner', [$this->element('grandchild-1')])
            ->build();

        $root1 = StoredElementBuilder::create('core:section', 'root-1')
            ->withSlot('main', [$childA, $this->element('child-b')])
            ->build();

        return new StoredTree([$root1, $this->element('root-2')]);
    }

    private function element(string $id): StoredElement
    {
        return StoredElementBuilder::create('core:text', $id)->build();
    }

    /**
     * @return list<string>
     */
    private function rootIds(StoredTree $tree): array
    {
        return array_map(static fn (StoredElement $root): string => $root->id, $tree->roots);
    }

    /**
     * The forest's structural shape, independent of which node instances make it up: a mutating operation may
     * return new instances for nodes it merely walked past, so comparing object identity would fail for reasons
     * unrelated to whether the operation actually changed anything.
     *
     * @return list<array<string, mixed>>
     */
    private function serialize(StoredTree $tree): array
    {
        return array_map(static fn (StoredElement $root): array => $root->jsonSerialize(), $tree->roots);
    }
}
