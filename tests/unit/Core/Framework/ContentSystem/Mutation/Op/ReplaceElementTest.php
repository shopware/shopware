<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(ReplaceElement::class)]
class ReplaceElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('keeps the element id while swapping the component')]
    public function testReplaceKeepsElementId(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old')];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('el', $result[0]->getId());
        static::assertSame('Sw:New', $result[0]->getComponent());
    }

    /**
     * @param array<string, mixed> $oldProperties
     * @param array<string, mixed> $expectedKept
     */
    #[DataProvider('carriesOverPropertiesProvider')]
    #[TestDox('carries over only primitive properties whose key and type match the new type')]
    public function testReplacePropertyCarryover(array $oldProperties, array $expectedKept): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], $oldProperties)];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame($expectedKept, $result[0]->getProperties());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function carriesOverPropertiesProvider(): iterable
    {
        yield 'matching string kept' => [['headline' => 'Hi'], ['headline' => 'Hi']];
        yield 'matching integer kept' => [['count' => 5], ['count' => 5]];
        yield 'matching number keeps an int' => [['ratio' => 5], ['ratio' => 5]];
        yield 'matching number keeps a float' => [['ratio' => 1.5], ['ratio' => 1.5]];
        yield 'matching boolean kept' => [['featured' => true], ['featured' => true]];
        yield 'mismatched type dropped' => [['count' => 'text'], []];
        yield 'float dropped from an integer property' => [['count' => 1.5], []];
        yield 'key absent from new type dropped' => [['ghost' => 'x'], []];
        yield 'scalar under a reference key dropped' => [['product' => 'oops'], []];
    }

    #[TestDox('reports static property values the new type cannot hold via droppedProperties')]
    public function testReplaceReportsDroppedProperties(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [
            'headline' => 'Hi',
            'ghost' => 'orphaned-value',
            'count' => 'not-an-int',
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertSame(['headline' => 'Hi'], $result[0]->getProperties());
        static::assertSame(['ghost' => 'orphaned-value', 'count' => 'not-an-int'], $replace->droppedProperties());
    }

    #[TestDox('resets droppedProperties on re-apply so a second run does not accumulate the first run drops')]
    public function testReplaceResetsDroppedPropertiesOnReapply(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $replace->apply([new ContentElement('el', 'Sw:Old', [], ['ghost' => 'first-run'])]);
        $replace->apply([new ContentElement('el', 'Sw:Old', [], ['count' => 'second-run'])]);

        static::assertSame(['count' => 'second-run'], $replace->droppedProperties());
    }

    #[TestDox('seeds the new type primitive default for a key the old element lacked')]
    public function testReplaceSeedsNewTypeDefaultForAbsentKey(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old')];

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('Default tagline', $result[0]->getProperty('tagline'));
    }

    #[TestDox('does not overwrite a carried-over authored value with the new type default')]
    public function testReplaceKeepsAuthoredValueOverNewTypeDefault(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['headline' => 'Authored'])];

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('Authored', $result[0]->getProperty('headline'));
    }

    #[TestDox('seeds the new type default for a key whose type-incompatible old value was dropped')]
    public function testReplaceSeedsNewTypeDefaultForDroppedIncompatibleKey(): void
    {
        $replace = new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $result = $replace->apply([new ContentElement('el', 'Sw:Old', [], ['count' => 'not-an-int'])]);

        static::assertSame(['count' => 'not-an-int'], $replace->droppedProperties());
        static::assertSame(7, $result[0]->getProperty('count'));
    }

    #[TestDox('does not throw and applies no additional wiring when the new type has no default specification')]
    public function testReplaceWithNoDefaultAppliesNothingExtra(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old')];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame([], $result[0]->getDataRequirements());
        static::assertSame([], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('preserves carried wiring for a shared key but still fill-applies the default for a key the carry left unwired')]
    public function testReplacePreservesCarriedWiringButFillAppliesDefaultForUnwiredKey(): void
    {
        $carriedConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);

        $old = ContentElementBuilder::create('Sw:Old', 'el')
            ->withDataRequirement('product', 'entity', $carriedConfig)
            ->withAttributedSpecification('product', 'core:carried-spec')
            ->build();

        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', [
            'product' => new LoaderBinding('entity', ['entity' => 'product', 'property' => 'productId']),
            'gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds']),
        ], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator($newConfig));
        $result = $replace->apply([$old]);

        static::assertSame($carriedConfig, $result[0]->getDataRequirements()['product']->config);
        static::assertSame(['product' => 'core:carried-spec', 'gallery' => 'core:Sw:New'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('rejects a new type with more than one default specification with a 409 naming the colliding qualified ids')]
    public function testReplaceWithAmbiguousDefaultThrows(): void
    {
        $first = new BindingSpecification('Sw:New', 'Sw:New', 'New', [], [], 'core');
        $second = new BindingSpecification('Sw:New', 'Sw:New', 'New', [], [], 'app1');

        $replace = new ReplaceElement(
            $this->registry(),
            'el',
            'Sw:New',
            $this->bindingRegistry(['core:Sw:New' => $first, 'app1:Sw:New' => $second]),
            $this->unboundApplicator(),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDefaultAmbiguous('Sw:New', ['core:Sw:New', 'app1:Sw:New']));
        $replace->apply([new ContentElement('el', 'Sw:Old')]);
    }

    #[TestDox('carries a stored value under a resolvedBy storage key when its shape matches the entity loader branch (a single id string)')]
    public function testReplaceCarriesStorageKeyMatchingEntityShape(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['mediaId' => 'media-1'])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])], [], 'core');

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class))))->apply($tree);

        static::assertSame('media-1', $result[0]->getProperty('mediaId'));
    }

    #[TestDox('carries a stored value under a resolvedBy storage key when its shape matches the entity_collection loader branch (a list of id strings)')]
    public function testReplaceCarriesStorageKeyMatchingEntityCollectionShape(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['galleryIds' => ['media-1', 'media-2']])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds'])], [], 'core');

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class))))->apply($tree);

        static::assertSame(['media-1', 'media-2'], $result[0]->getProperty('galleryIds'));
    }

    #[TestDox('drops and reports a stored value under a resolvedBy storage key whose shape does not match its loader branch')]
    public function testReplaceDropsAndReportsStorageKeyShapeMismatch(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['mediaId' => ['not', 'a', 'string']])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertFalse($result[0]->hasProperty('mediaId'));
        static::assertSame(['mediaId' => ['not', 'a', 'string']], $replace->droppedProperties());
    }

    #[TestDox('drops and reports a stored value under a resolvedBy storage key whose loader is not one of the two built-in resolvedBy loaders')]
    public function testReplaceDropsStorageKeyWiredByNonBuiltinLoader(): void
    {
        // carryableStorageKeys() maps only resolves entries whose loader passes ResolvedByLoaderBranch::fromLoaderSource();
        // the "navigation" loader is neither built-in resolvedBy loader, so it contributes no carryable storage key and
        // the correspondingly-named stored value falls through to droppedProperties. The config still names "activeId"
        // via a "property" key so the drop is attributable solely to the loader-source gate: if it regressed to accept
        // "navigation", the stored string would match the entity branch and be carried instead.
        $tree = [new ContentElement('el', 'Sw:Old', [], ['activeId' => 'category-1'])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['navigation' => new LoaderBinding('navigation', ['entity' => 'category', 'property' => 'activeId'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertFalse($result[0]->hasProperty('activeId'));
        static::assertSame(['activeId' => 'category-1'], $replace->droppedProperties());
    }

    #[TestDox('applies the declared-primitive rule, not the storage-key shape check, for a key that is both, dropping a value the primitive type rejects')]
    public function testReplaceDeclaredPrimitiveRuleWinsDroppingTypeMismatchOverStorageKeyShape(): void
    {
        // count is a declared integer primitive of Sw:New AND the default wires it as an entity storage key (config
        // property "count"). The declared-primitive rule runs first and rejects the string, so the entity branch's
        // shape check (which accepts any string) is never consulted; a flipped precedence would carry the string.
        $tree = [new ContentElement('el', 'Sw:Old', [], ['count' => 'some-id-string'])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['count' => new LoaderBinding('entity', ['entity' => 'category', 'property' => 'count'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertFalse($result[0]->hasProperty('count'));
        static::assertSame(['count' => 'some-id-string'], $replace->droppedProperties());
    }

    #[TestDox('applies the declared-primitive rule, not the storage-key shape check, for a key that is both, carrying a value the primitive type accepts')]
    public function testReplaceDeclaredPrimitiveRuleWinsCarryingTypeMatchOverStorageKeyShape(): void
    {
        // count is a declared integer primitive and also wired as an entity storage key. The declared-primitive rule
        // carries the matching integer; the entity branch's shape check (a string only) would have dropped it, so a
        // flipped precedence would report it dropped instead.
        $tree = [new ContentElement('el', 'Sw:Old', [], ['count' => 5])];
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['count' => new LoaderBinding('entity', ['entity' => 'category', 'property' => 'count'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertSame(5, $result[0]->getProperty('count'));
        static::assertSame([], $replace->droppedProperties());
    }

    #[TestDox('keeps wiring whose key matches a new-type reference property and does not report it as dropped')]
    public function testReplaceKeepsMatchingWiring(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = [new ContentElement('el', 'Sw:Old', ['product' => $requirement])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertSame(['product' => $requirement], $result[0]->getDataRequirements());
        static::assertSame([], $replace->droppedWiring());
    }

    #[TestDox('drops wiring whose key is absent from the new type and reports it without re-mapping')]
    public function testReplaceDropsAndReportsAbsentWiring(): void
    {
        $requirement = new DataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = [new ContentElement('el', 'Sw:Old', ['legacy' => $requirement])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertSame([], $result[0]->getDataRequirements());
        static::assertSame(['legacy'], $replace->droppedWiring());
    }

    #[TestDox('keeps the attributed specification for a carried wired key and drops it for a wired key the new type no longer has')]
    public function testReplaceKeepsAttributedSpecificationForCarriedKeyAndDropsForAbsentKey(): void
    {
        $old = ContentElementBuilder::create('Sw:Old', 'el')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withDataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withAttributedSpecification('product', 'spec-product')
            ->withAttributedSpecification('legacy', 'spec-legacy')
            ->build();

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply([$old]);

        static::assertSame(['product' => 'spec-product'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('reports a dropped context provider and consumer key once each')]
    public function testReplaceReportsDroppedContextWiring(): void
    {
        $definitions = new ContextDefinitions(
            ['legacyProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
            ['legacyConsumer' => new ContextConsumer(ContextType::Single, true)],
        );
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [], $definitions)];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $replace->apply($tree);

        static::assertSame(['legacyProvider', 'legacyConsumer'], $replace->droppedWiring());
    }

    #[TestDox('keeps context definitions whose key matches a new-type reference property and drops the rest')]
    public function testReplaceContextDefinitionsCarryover(): void
    {
        $kept = new ContextConsumer(ContextType::Single, true);
        $dropped = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $definitions = new ContextDefinitions(['legacy' => $dropped], ['product' => $kept]);
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [], $definitions)];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame(['product' => $kept], $result[0]->getAcceptsContext());
        static::assertSame([], $result[0]->getProvidesContext());
    }

    #[TestDox('keeps the children of a slot that exists in the new type')]
    public function testReplaceKeepsKnownSlot(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame('child', $children[0]->getId());
    }

    #[TestDox('detaches children of a slot absent from the new type into orphaned without re-mapping')]
    public function testReplaceOrphansAbsentSlotChildren(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'legacy' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertFalse($result[0]->hasSlots());
        static::assertSame(['child'], array_map(static fn (ContentElement $e): string => $e->getId(), $replace->orphaned()));
    }

    #[TestDox('carries the element style over to the replacement unconditionally on a type swap')]
    public function testReplaceCarriesStyleUnconditionally(): void
    {
        $style = new ElementStyle(['col-span' => ['md' => 6], 'display' => ['xs' => false]]);
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [], new ContextDefinitions([], []), $style)];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame($style->toArray(), $result[0]->getStyle()->toArray());
    }

    #[TestDox('reports the replaced element and its kept descendants as affected')]
    public function testReplaceAffectedCoversKeptSubtree(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $replace->apply($tree);

        static::assertSame(['el', 'child'], $replace->affected());
    }

    #[TestDox('does not mutate the input tree')]
    public function testReplaceDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['headline' => 'Hi'], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];
        $before = $this->snapshotTree($tree);

        (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects an unregistered new type with a 400')]
    public function testReplaceUnknownNewTypeRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:Ghost', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $replace->apply([new ContentElement('el', 'Sw:Old')]);
    }

    #[TestDox('rejects replacing an element absent from the tree with a 400')]
    public function testReplaceMissingElementRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'ghost', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $replace->apply([new ContentElement('el', 'Sw:Old')]);
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $spec = new ContentSystemElementTypeSpecification(
            'Sw:New',
            'New',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'headline' => $this->primitive('string'),
                'count' => $this->primitive('integer'),
                'ratio' => $this->primitive('number'),
                'featured' => $this->primitive('boolean'),
                'product' => $this->reference(),
            ],
            [new SlotSpecification('content', null, [], '')],
        );
        $specs = ['Sw:New' => $spec];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    private function registryWithDefaults(): AbstractContentSystemElementTypeRegistry
    {
        $specs = ['Sw:New' => ContentSystemElementTypeSpecificationBuilder::create('Sw:New')
            ->primitive('headline', 'string', required: true, default: 'Default headline')
            ->primitive('count', 'integer', required: true, default: 7)
            ->primitive('tagline', 'string', required: true, default: 'Default tagline')
            ->build()];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    private function primitive(string $type): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, null), false, '', '', null);
    }

    private function reference(): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType('SomeEntity', false, null, null), false, '', '', null);
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
}
