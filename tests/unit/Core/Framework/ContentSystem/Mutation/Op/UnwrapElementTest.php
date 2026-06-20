<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;

/**
 * @internal
 */
#[CoversClass(UnwrapElement::class)]
class UnwrapElementTest extends TestCase
{
    #[TestDox('replaces the container with its slot children at the root')]
    public function testUnwrapReplacesContainerWithChildren(): void
    {
        $tree = [new ContentElement('container', 'Sw:Container', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
        ])];

        $result = (new UnwrapElement('container'))->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
    }

    #[TestDox('hoists the children into the parent slot at the container position')]
    public function testUnwrapHoistsIntoParentSlotAtPosition(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('x', 'Sw:Block'),
                new ContentElement('container', 'Sw:Container', [], [], [
                    'items' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
                ]),
                new ContentElement('y', 'Sw:Block'),
            ]),
        ])];

        $result = (new UnwrapElement('container'))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['x', 'a', 'b', 'y'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
    }

    #[TestDox('reports the whole hoisted forest as affected, including grandchildren that lose the container scope')]
    public function testUnwrapAffectedAreHoistedSubtrees(): void
    {
        $tree = [new ContentElement('container', 'Sw:Container', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'Sw:Block', [], [], [
                    'inner' => new SlotContent([new ContentElement('grandchild', 'Sw:Block')]),
                ]),
                new ContentElement('b', 'Sw:Block'),
            ]),
        ])];

        $unwrap = new UnwrapElement('container');
        $unwrap->apply($tree);

        static::assertSame(['a', 'grandchild', 'b'], $unwrap->affected());
    }

    #[TestDox('flattens children across all container slots in slot order')]
    public function testUnwrapFlattensAllSlots(): void
    {
        $tree = [new ContentElement('container', 'Sw:Container', [], [], [
            'header' => new SlotContent([new ContentElement('a', 'Sw:Block')]),
            'body' => new SlotContent([new ContentElement('b', 'Sw:Block')]),
        ])];

        $result = (new UnwrapElement('container'))->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
    }

    #[TestDox('removes an empty container and hoists nothing')]
    public function testUnwrapEmptyContainerJustRemovesIt(): void
    {
        $tree = [new ContentElement('container', 'Sw:Container'), new ContentElement('keep', 'Sw:Block')];

        $unwrap = new UnwrapElement('container');
        $result = $unwrap->apply($tree);

        static::assertSame(['keep'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
        static::assertSame([], $unwrap->affected());
    }

    #[TestDox('rejects unwrapping a container absent from the tree with a 400')]
    public function testUnwrapMissingContainerRejected(): void
    {
        $unwrap = new UnwrapElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $unwrap->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('does not mutate the input tree')]
    public function testUnwrapDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('container', 'Sw:Container', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block')]),
        ])];

        (new UnwrapElement('container'))->apply($tree);

        static::assertCount(1, $tree);
        static::assertTrue($tree[0]->hasSlots());
    }
}
