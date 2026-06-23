<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AttachElement::class)]
class AttachElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('appends the supplied subtree at the root with a server-minted id')]
    public function testAttachesAtRootWithMintedId(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];

        $result = (new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card')))->apply($tree);

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

        $result = (new AttachElement($this->registry(), $incoming))->apply([]);

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

        $attach = new AttachElement($this->registry(), $incoming);
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

        $result = (new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'), 'parent', 'content', 0))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertCount(2, $children);
        static::assertNotSame('incoming', $children[0]->getId());
        static::assertSame('first', $children[1]->getId());
    }

    #[TestDox('clamps an out-of-range index, appending the supplied subtree to the end of the target list')]
    public function testAttachClampsOutOfRangeIndex(): void
    {
        $tree = [new ContentElement('block-a', 'Sw:Card'), new ContentElement('block-b', 'Sw:Card')];

        $result = (new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'), null, null, 99))->apply($tree);

        static::assertCount(3, $result);
        static::assertSame(['block-a', 'block-b'], [$result[0]->getId(), $result[1]->getId()]);
        static::assertNotSame('incoming', $result[2]->getId());
    }

    #[TestDox('detaches nothing: orphaned and dropped wiring stay empty')]
    public function testAttachDetachesNothing(): void
    {
        $attach = new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'));
        $attach->apply([]);

        static::assertSame([], $attach->orphaned());
        static::assertSame([], $attach->droppedWiring());
    }

    #[TestDox('does not mutate the input tree in place')]
    public function testAttachDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('parent', 'Sw:Block', [], ['title' => 'Section'], [
            'content' => new SlotContent([new ContentElement('first', 'Sw:Card')]),
        ])];
        $before = $this->snapshotTree($tree);

        (new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'), 'parent', 'content'))->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects an unregistered root component with a 400')]
    public function testAttachUnregisteredComponentRejected(): void
    {
        $attach = new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Ghost'));

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $attach->apply([]);
    }

    #[TestDox('rejects attaching into a parent absent from the tree with a 400')]
    public function testAttachIntoMissingParentRejected(): void
    {
        $attach = new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'), 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $attach->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('rejects attaching into a parent without naming a slot with a 400')]
    public function testAttachIntoParentWithoutSlotRejected(): void
    {
        $attach = new AttachElement($this->registry(), new ContentElement('incoming', 'Sw:Card'), 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $attach->apply([new ContentElement('parent', 'Sw:Block')]);
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registered = ['Sw:Card', 'Sw:Block'];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, $registered, true));

        return $registry;
    }
}
