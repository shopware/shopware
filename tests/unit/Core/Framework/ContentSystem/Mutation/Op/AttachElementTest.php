<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttachElement::class)]
class AttachElementTest extends TestCase
{
    #[TestDox('appends the supplied subtree at the root with a server-minted id')]
    public function testAttachesAtRootWithMintedId(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'Sw:Block')]);

        $result = (new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card')))->apply($tree);

        static::assertCount(2, $result->roots);
        static::assertSame('existing', $result->roots[0]->id);
        static::assertSame('Sw:Card', $result->roots[1]->component);
        static::assertNotSame('incoming', $result->roots[1]->id);
        static::assertTrue(Uuid::isValid($result->roots[1]->id));
    }

    #[TestDox('remints every id in the supplied subtree, never trusting client ids')]
    public function testRemintsEverySubtreeId(): void
    {
        $incoming = new StoredElement('incoming', 'Sw:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card')],
        ]);

        $result = (new AttachElement($this->registry(), $incoming))->apply(new StoredTree([]));

        $attached = $result->roots[0];
        $child = $attached->slots['content'][0];
        static::assertNotSame('incoming', $attached->id);
        static::assertNotSame('incoming-child', $child->id);
        static::assertSame('Sw:Card', $child->component);
    }

    #[TestDox('reports every reminted subtree id as affected')]
    public function testAffectedAreMintedSubtreeIds(): void
    {
        $incoming = new StoredElement('incoming', 'Sw:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card')],
        ]);

        $attach = new AttachElement($this->registry(), $incoming);
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        static::assertSame([$attached->id, $attached->slots['content'][0]->id], $attach->affected());
    }

    #[TestDox('attaches the subtree into a parent slot at an explicit index')]
    public function testAttachesIntoParentSlotAtIndex(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [new StoredElement('first', 'Sw:Card')],
        ])]);

        $result = (new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card'), 'parent', 'content', 0))->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertCount(2, $children);
        static::assertNotSame('incoming', $children[0]->id);
        static::assertSame('first', $children[1]->id);
    }

    #[TestDox('clamps an out-of-range index, appending the supplied subtree to the end of the target list')]
    public function testAttachClampsOutOfRangeIndex(): void
    {
        $tree = new StoredTree([new StoredElement('block-a', 'Sw:Card'), new StoredElement('block-b', 'Sw:Card')]);

        $result = (new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card'), null, null, 99))->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame(['block-a', 'block-b'], [$result->roots[0]->id, $result->roots[1]->id]);
        static::assertNotSame('incoming', $result->roots[2]->id);
    }

    #[TestDox('detaches nothing: orphaned and dropped wiring stay empty')]
    public function testAttachDetachesNothing(): void
    {
        $attach = new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card'));
        $attach->apply(new StoredTree([]));

        static::assertSame([], $attach->orphaned());
        static::assertSame([], $attach->droppedWiring());
    }

    #[TestDox('rejects an unregistered root component with a 400')]
    public function testAttachUnregisteredComponentRejected(): void
    {
        $attach = new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Ghost'));

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $attach->apply(new StoredTree([]));
    }

    #[TestDox('rejects attaching into a parent absent from the tree with a 400')]
    public function testAttachIntoMissingParentRejected(): void
    {
        $attach = new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card'), 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $attach->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }

    #[TestDox('rejects attaching into a parent without naming a slot with a 400')]
    public function testAttachIntoParentWithoutSlotRejected(): void
    {
        $attach = new AttachElement($this->registry(), new StoredElement('incoming', 'Sw:Card'), 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $attach->apply(new StoredTree([new StoredElement('parent', 'Sw:Block')]));
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registered = ['Sw:Card', 'Sw:Block'];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, $registered, true));

        return $registry;
    }
}
