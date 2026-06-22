<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AttachElement::class)]
class AttachElementTest extends TestCase
{
    #[TestDox('appends the supplied subtree at the root with a server-minted id')]
    public function testAttachesAtRootWithMintedId(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];

        $result = (new AttachElement(new ContentElement('incoming', 'Sw:Card')))->apply($tree);

        static::assertCount(2, $result);
        static::assertSame('existing', $result[0]->getId());
        static::assertSame('Sw:Card', $result[1]->getComponent());
        static::assertNotSame('incoming', $result[1]->getId());
        static::assertTrue(Uuid::isValid($result[1]->getId()));
    }

    #[TestDox('remints every id in the supplied subtree, never trusting client ids')]
    public function testRemintsEverySubtreeId(): void
    {
        $incoming = new ContentElement('incoming', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('incoming-child', 'Sw:Card')]),
        ]);

        $result = (new AttachElement($incoming))->apply([]);

        $attached = $result[0];
        $child = array_values($attached->getSlots()['content']->getElements())[0];
        static::assertNotSame('incoming', $attached->getId());
        static::assertNotSame('incoming-child', $child->getId());
        static::assertSame('Sw:Card', $child->getComponent());
    }

    #[TestDox('reports every reminted subtree id as affected')]
    public function testAffectedAreMintedSubtreeIds(): void
    {
        $incoming = new ContentElement('incoming', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('incoming-child', 'Sw:Card')]),
        ]);

        $attach = new AttachElement($incoming);
        $result = $attach->apply([]);

        $attached = $result[0];
        $child = array_values($attached->getSlots()['content']->getElements())[0];
        static::assertSame([$attached->getId(), $child->getId()], $attach->affected());
    }

    #[TestDox('attaches the subtree into a parent slot at an explicit index')]
    public function testAttachesIntoParentSlotAtIndex(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('first', 'Sw:Card')]),
        ])];

        $result = (new AttachElement(new ContentElement('incoming', 'Sw:Card'), 'parent', 'content', 0))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertCount(2, $children);
        static::assertNotSame('incoming', $children[0]->getId());
        static::assertSame('first', $children[1]->getId());
    }

    #[TestDox('rejects attaching into a parent absent from the tree with a 400')]
    public function testAttachIntoMissingParentRejected(): void
    {
        $attach = new AttachElement(new ContentElement('incoming', 'Sw:Card'), 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $attach->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('rejects attaching into a parent without naming a slot with a 400')]
    public function testAttachIntoParentWithoutSlotRejected(): void
    {
        $attach = new AttachElement(new ContentElement('incoming', 'Sw:Card'), 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $attach->apply([new ContentElement('parent', 'Sw:Block')]);
    }

    #[TestDox('detaches nothing: orphaned and dropped wiring stay empty')]
    public function testAttachDetachesNothing(): void
    {
        $attach = new AttachElement(new ContentElement('incoming', 'Sw:Card'));
        $attach->apply([]);

        static::assertSame([], $attach->orphaned());
        static::assertSame([], $attach->droppedWiring());
    }

    #[TestDox('does not mutate the input tree in place')]
    public function testAttachDoesNotMutateInput(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('first', 'Sw:Card')]),
        ]);

        (new AttachElement(new ContentElement('incoming', 'Sw:Card'), 'parent', 'content'))->apply([$parent]);

        static::assertCount(1, $parent->getSlots()['content']->getElements());
    }
}
