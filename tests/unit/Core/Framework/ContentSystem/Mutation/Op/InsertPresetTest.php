<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertPreset;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InsertPreset::class)]
class InsertPresetTest extends TestCase
{
    #[TestDox('inserts every preset root at the root in order, each with a server-minted id')]
    public function testInsertsAllRootsAtRootInOrder(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'Sw:Block')]);
        $elements = [new StoredElement('first', 'Sw:Card'), new StoredElement('second', 'Sw:Block')];

        $result = (new InsertPreset($this->registry(), $elements))->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame('existing', $result->roots[0]->id);
        static::assertSame('Sw:Card', $result->roots[1]->component);
        static::assertSame('Sw:Block', $result->roots[2]->component);
        static::assertNotSame('first', $result->roots[1]->id);
        static::assertNotSame('second', $result->roots[2]->id);
        static::assertTrue(Uuid::isValid($result->roots[1]->id));
    }

    #[TestDox('remints every id in every supplied subtree')]
    public function testRemintsEverySubtreeId(): void
    {
        $elements = [
            new StoredElement('root', 'Sw:Block', [], [], [
                'content' => [new StoredElement('child', 'Sw:Card')],
            ]),
        ];

        $result = (new InsertPreset($this->registry(), $elements))->apply(new StoredTree([]));

        $inserted = $result->roots[0];
        static::assertNotSame('root', $inserted->id);
        static::assertNotSame('child', $inserted->slots['content'][0]->id);
        static::assertSame('Sw:Card', $inserted->slots['content'][0]->component);
    }

    #[TestDox('reports every reminted subtree id across all roots as affected')]
    public function testAffectedAreAllMintedSubtreeIds(): void
    {
        $elements = [
            new StoredElement('root', 'Sw:Block', [], [], [
                'content' => [new StoredElement('child', 'Sw:Card')],
            ]),
            new StoredElement('second', 'Sw:Card'),
        ];

        $insert = new InsertPreset($this->registry(), $elements);
        $result = $insert->apply(new StoredTree([]));

        $first = $result->roots[0];
        $second = $result->roots[1];
        static::assertSame(
            [$first->id, $first->slots['content'][0]->id, $second->id],
            $insert->affected(),
        );
    }

    #[TestDox('inserts the whole preset into a parent slot at an explicit index')]
    public function testInsertsIntoParentSlotAtIndex(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [new StoredElement('first', 'Sw:Card')],
        ])]);
        $elements = [new StoredElement('a', 'Sw:Card'), new StoredElement('b', 'Sw:Card')];

        $result = (new InsertPreset($this->registry(), $elements, 'parent', 'content', 0))->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertCount(3, $children);
        static::assertSame('first', $children[2]->id);
        static::assertSame('Sw:Card', $children[0]->component);
    }

    #[TestDox('an empty preset inserts nothing')]
    public function testEmptyPresetInsertsNothing(): void
    {
        $insert = new InsertPreset($this->registry(), []);
        $result = $insert->apply(new StoredTree([new StoredElement('existing', 'Sw:Block')]));

        static::assertCount(1, $result->roots);
        static::assertSame([], $insert->affected());
    }

    #[TestDox('rejects an unregistered root component with a 400')]
    public function testUnregisteredComponentRejected(): void
    {
        $insert = new InsertPreset($this->registry(), [new StoredElement('ghost', 'Sw:Ghost')]);

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $insert->apply(new StoredTree([]));
    }

    #[TestDox('rejects inserting into a parent absent from the tree with a 400')]
    public function testInsertIntoMissingParentRejected(): void
    {
        $insert = new InsertPreset($this->registry(), [new StoredElement('a', 'Sw:Card')], 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $insert->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }

    #[TestDox('rejects inserting into a parent without naming a slot with a 400')]
    public function testInsertIntoParentWithoutSlotRejected(): void
    {
        $insert = new InsertPreset($this->registry(), [new StoredElement('a', 'Sw:Card')], 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $insert->apply(new StoredTree([new StoredElement('parent', 'Sw:Block')]));
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registered = ['Sw:Card', 'Sw:Block'];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, $registered, true));

        return $registry;
    }
}
