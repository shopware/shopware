<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator());
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
        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator());
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

        $insert = new InsertElement($this->registry(['Sw:Card' => $spec]), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $insert->apply([]);

        static::assertSame(['headline' => 'Hello'], $result[0]->getProperties());
    }

    #[TestDox('splices the new element into a parent slot at the given index')]
    public function testInsertIntoParentSlotAtIndex(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'Sw:Block'), new ContentElement('b', 'Sw:Block')]),
        ]);

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent', slot: 'content', index: 1);
        $result = $insert->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['a', 'Sw:Card', 'b'], [$children[0]->getId(), $children[1]->getComponent(), $children[2]->getId()]);
    }

    #[TestDox('prepends to the root when index zero is given without a parent')]
    public function testInsertAtRootIndexZero(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator(), index: 0);
        $result = $insert->apply($tree);

        static::assertSame('Sw:Card', $result[0]->getComponent());
        static::assertSame('existing', $result[1]->getId());
    }

    #[TestDox('rejects an unregistered type with a 400')]
    public function testInsertUnknownTypeRejected(): void
    {
        $insert = new InsertElement($this->registry([]), 'Sw:Ghost', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $insert->apply([]);
    }

    #[TestDox('rejects a parented insert without a slot with a 400')]
    public function testInsertParentWithoutSlotRejected(): void
    {
        $parent = new ContentElement('parent', 'Sw:Block');

        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $insert->apply([$parent]);
    }

    #[TestDox('rejects an insert into a missing parent with a 400')]
    public function testInsertMissingParentRejected(): void
    {
        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'ghost', slot: 'content');

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

        $result = (new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent', slot: 'content'))->apply($tree);

        static::assertSame($style->toArray(), $result[0]->getStyle()->toArray());
        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('applies the binding specification onto the freshly scaffolded element with its wiring, seeded input default, and attribution')]
    public function testInsertAppliesBindingWiringSeededDefaultAndAttribution(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $spec = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(true, 'seeded', false)],
            'core',
        );

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry(['core:media-picker' => $spec]),
            $this->applicator($config),
            'core:media-picker',
        );
        $result = $insert->apply([]);

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result[0]->getDataRequirements());
        static::assertSame('seeded', $result[0]->getProperty('mediaId'));
        static::assertSame(['media' => 'core:media-picker'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('does not throw and applies no wiring or attribution when the type has no default specification')]
    public function testInsertWithNoDefaultAppliesNothing(): void
    {
        $insert = new InsertElement($this->registryWith('Sw:Card'), 'Sw:Card', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $insert->apply([]);

        static::assertSame([], $result[0]->getDataRequirements());
        static::assertSame([], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('auto-applies the type default specification onto a fresh insert with no explicit bindingSpecificationId, attributed to its own qualified id')]
    public function testInsertAutoAppliesTypeDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $default = new BindingSpecification(
            'Sw:Media:Image',
            'Sw:Media:Image',
            'Image',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            [],
            'core',
        );

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry(['core:Sw:Media:Image' => $default]),
            $this->applicator($config),
        );
        $result = $insert->apply([]);

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result[0]->getDataRequirements());
        static::assertSame(['media' => 'core:Sw:Media:Image'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('fill-applies the type default first, then applies the explicit binding specification on top so only the shared key becomes attributed to the explicit choice')]
    public function testInsertExplicitBindingAppliesOnTopOfDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $default = new BindingSpecification(
            'Sw:Media:Image',
            'Sw:Media:Image',
            'Image',
            [
                'media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId']),
                'gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds']),
            ],
            [],
            'core',
        );
        $explicit = new BindingSpecification('gallery-pick', 'Sw:Media:Image', 'Gallery pick', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'galleryPickId'])], [], 'core');

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry(['core:Sw:Media:Image' => $default, 'core:gallery-pick' => $explicit]),
            $this->applicator($config),
            'core:gallery-pick',
        );
        $result = $insert->apply([]);

        static::assertSame(['media' => 'core:gallery-pick', 'gallery' => 'core:Sw:Media:Image'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('rejects a type with more than one default specification with a 409 naming the colliding qualified ids')]
    public function testInsertWithAmbiguousDefaultThrows(): void
    {
        $first = new BindingSpecification('Sw:Media:Image', 'Sw:Media:Image', 'Image', [], [], 'core');
        $second = new BindingSpecification('Sw:Media:Image', 'Sw:Media:Image', 'Image', [], [], 'app1');

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry(['core:Sw:Media:Image' => $first, 'app1:Sw:Media:Image' => $second]),
            $this->unboundApplicator(),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDefaultAmbiguous('Sw:Media:Image', ['core:Sw:Media:Image', 'app1:Sw:Media:Image']));
        $insert->apply([]);
    }

    #[TestDox('rejects an unknown bindingSpecificationId with a 400 before any tree change')]
    public function testInsertUnknownBindingRejectedTreeUntouched(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];
        $before = $this->snapshotTree($tree);

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry([]),
            $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)),
            'core:ghost',
        );

        try {
            $insert->apply($tree);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_NOT_FOUND, $e->getErrorCode());
        }

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects a binding specification whose type does not match the inserted type with a 400 before any tree change')]
    public function testInsertMismatchedBindingTypeRejectedTreeUntouched(): void
    {
        $tree = [new ContentElement('existing', 'Sw:Block')];
        $before = $this->snapshotTree($tree);

        $spec = new BindingSpecification('media-picker', 'Sw:Other', 'label', ['media' => new LoaderBinding('entity', [])], [], 'core');

        $insert = new InsertElement(
            $this->registryWith('Sw:Media:Image'),
            'Sw:Media:Image',
            $this->bindingRegistry(['core:media-picker' => $spec]),
            $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)),
            'core:media-picker',
        );

        try {
            $insert->apply($tree);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_TYPE_MISMATCH, $e->getErrorCode());
        }

        $this->assertInputTreeUnmutated($before, $tree);
    }

    private function registryWith(string $type): AbstractContentSystemElementTypeRegistry
    {
        return $this->registry([$type => $this->spec($type, [])]);
    }

    /**
     * @param array<string, BindingSpecification> $specs
     */
    private function bindingRegistry(array $specs): AbstractContentSystemBindingSpecificationRegistry
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn($specs);

        return $registry;
    }

    private function applicator(AbstractContentDataLoaderConfig $config): BindingApplicator
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($config);

        return new BindingApplicator($serializers);
    }

    private function unboundApplicator(): BindingApplicator
    {
        return new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class));
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
