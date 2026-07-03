<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(InsertElement::class)]
class InsertElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('appends a fresh element of the type to the root with a server-minted id and no seeded style')]
    public function testInsertAppendsRootElement(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card');
        $result = $insert->apply($tree);

        static::assertCount(2, $result);
        static::assertSame('existing', $result[0]->getId());
        static::assertSame('Sw:Card', $result[1]->getComponent());
        static::assertTrue(Uuid::isValid($result[1]->getId()));
        static::assertTrue($result[1]->getStyle()->isEmpty());
    }

    #[TestDox('reports the minted id as the only affected element')]
    public function testInsertAffectedIsMintedId(): void
    {
        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card');
        $result = $insert->apply([]);

        static::assertSame([$result[0]->getId()], $insert->affected());
    }

    #[TestDox('seeds only primitive properties that declare a default')]
    public function testInsertSeedsPrimitiveDefaultsOnly(): void
    {
        $spec = $this->spec('Sw:Card', [
            'headline' => $this->primitive('string', 'Hello'),
            'count' => $this->primitive('integer', null),
            'product' => $this->reference(),
        ]);

        $insert = new InsertElement($this->registry(['Sw:Card' => $spec]), 'Sw:Card');
        $result = $insert->apply([]);

        static::assertSame(['headline' => 'Hello'], $result[0]->getProperties());
    }

    #[TestDox('splices the new element into a parent slot at the given index')]
    public function testInsertIntoParentSlotAtIndex(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
        ]);

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', 'parent', 'content', 1);
        $result = $insert->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['a', 'Sw:Card', 'b'], [$children[0]->getId(), $children[1]->getComponent(), $children[2]->getId()]);
    }

    #[TestDox('prepends to the root when index zero is given without a parent')]
    public function testInsertAtRootIndexZero(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', index: 0);
        $result = $insert->apply($tree);

        static::assertSame('Sw:Card', $result[0]->getComponent());
        static::assertSame('existing', $result[1]->getId());
    }

    #[TestDox('rejects an unregistered type with a 400')]
    public function testInsertUnknownTypeRejected(): void
    {
        $insert = new InsertElement($this->registry([]), 'Sw:Ghost');

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $insert->apply([]);
    }

    #[TestDox('rejects a parented insert without a slot with a 400')]
    public function testInsertParentWithoutSlotRejected(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block');

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $insert->apply([$parent]);
    }

    #[TestDox('rejects an insert into a missing parent with a 400')]
    public function testInsertMissingParentRejected(): void
    {
        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $insert->apply([new ContentElement('other', 'Sw:Block')]);
    }

    #[TestDox('preserves the parent style and does not mutate the input parent in place when inserting into its slot')]
    public function testInsertIntoSlotPreservesParentStyleAndDoesNotMutateInput(): void
    {
        $style = new ElementStyle(['padding' => ['md' => '1rem']]);
        $tree = [new ContentElement('parent', 'Sw:Block', [], ['title' => 'Section'], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block')]),
        ], new ContextDefinitions([], []), $style)];
        $before = $this->snapshotTree($tree);

        $result = (new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', 'parent', 'content'))->apply($tree);

        static::assertSame($style->toArray(), $result[0]->getStyle()->toArray());
        $this->assertInputTreeUnmutated($before, $tree);
    }

    private function registryWith(string $type): AbstractContentSystemElementTypeRegistry
    {
        return $this->registry([$type => $this->spec($type, [])]);
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specs
     */
    private function registry(array $specs): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param array<string, PropertySpecification> $properties
     */
    private function spec(string $name, array $properties): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification($name, $name, '', null, null, new CopilotSpecification('', []), $properties, []);
    }

    private function primitive(string $type, string|int|float|bool|null $default): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, $default), false, '', '', null);
    }

    private function reference(): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType('SomeEntity', false, null, null), false, '', '', null);
    }
}
