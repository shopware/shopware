<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReplaceElement::class)]
class ReplaceElementTest extends TestCase
{
    #[TestDox('keeps the element id while swapping the component, applying no wiring when the new type has no default specification')]
    public function testReplaceKeepsElementId(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old')]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry())))->apply($tree);

        static::assertSame('el', $result->roots[0]->id);
        static::assertSame('Sw:New', $result->roots[0]->component);
        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame([], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('reports the re-scaffolded node as created and never the children it carried over')]
    public function testReplaceCreatedIsTheReplacementNodeOnly(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'content' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $replace->apply($tree);

        static::assertSame(['el'], $replace->created());
        static::assertSame($replace->created(), $replace->freshlyScaffolded());
    }

    #[TestDox('keeps wiring whose key matches a new-type reference property and does not report it as dropped')]
    public function testReplaceKeepsMatchingWiring(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['product' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame(['product' => $requirement], $result->roots[0]->dataRequirements);
        static::assertSame([], $replace->droppedWiring());
    }

    #[TestDox('keeps the children of a slot that exists in the new type')]
    public function testReplaceKeepsKnownSlot(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'content' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame('child', $result->roots[0]->slots['content'][0]->id);
        static::assertSame([], $replace->orphaned());
    }

    #[TestDox('keeps context definitions whose key matches a new-type reference property and drops the rest')]
    public function testReplaceContextDefinitionsCarryover(): void
    {
        $kept = new ContextConsumer(ContextType::Single, true);
        $keptProvider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $dropped = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $definitions = new ContextDefinitions(['legacy' => $dropped, 'product' => $keptProvider], ['product' => $kept]);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], $definitions)]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame(['product' => $kept], $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame(['product' => $keptProvider], $result->roots[0]->contextDefinitions->getAllProviders());
        static::assertSame(['legacy'], $replace->droppedWiring());
    }

    #[TestDox('carries the element style over to the replacement unconditionally on a type swap')]
    public function testReplaceCarriesStyleUnconditionally(): void
    {
        $style = new ElementStyle(['col-span' => ['md' => 6], 'display' => ['xs' => false]]);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], new ContextDefinitions([], []), $style)]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry())))->apply($tree);

        static::assertSame($style->toArray(), $result->roots[0]->style->toArray());
    }

    /**
     * @param array<string, mixed> $oldProperties
     * @param array<string, mixed> $expectedKept
     */
    #[DataProvider('carriesOverPropertiesProvider')]
    #[TestDox('carries over only primitive properties whose key and type match the new type')]
    public function testReplacePropertyCarryover(array $oldProperties, array $expectedKept): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperties($oldProperties)->build()]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry())))->apply($tree);

        static::assertSame($expectedKept, $this->rawProperties($result->roots[0]));
    }

    #[TestDox('does not overwrite a carried-over authored value with the new type default')]
    public function testReplaceKeepsAuthoredValueOverNewTypeDefault(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('headline', 'Authored')->build()]);

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registryWithDefaults())))->apply($tree);

        static::assertSame('Authored', $result->roots[0]->property('headline')?->jsonSerialize());
    }

    /**
     * @param array<int, string>|string $propertyValue
     * @param array<int, string>|string $expectedValue
     */
    #[DataProvider('carriesStorageKeyMatchingLoaderShapeProvider')]
    #[TestDox('carries a stored value under a resolvedBy storage key when its shape matches the loader branch')]
    public function testReplaceCarriesStorageKeyMatchingLoaderShape(string $propertyKey, mixed $propertyValue, string $loaderType, mixed $expectedValue): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty($propertyKey, $propertyValue)->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['ref' => new LoaderBinding($loaderType, ['entity' => 'media', 'property' => $propertyKey])], [], 'core');

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)), mappingInspector: $this->mappingInspector($this->registry())))->apply($tree);

        static::assertSame($expectedValue, $result->roots[0]->property($propertyKey)?->jsonSerialize());
    }

    /**
     * @param array<string, mixed> $expectedProperties
     * @param array<string, mixed> $expectedDrops
     */
    #[DataProvider('declaredPrimitiveRuleWinsOverStorageKeyShapeProvider')]
    #[TestDox('applies the declared-primitive rule, not the storage-key shape check, for a key that is both: $_dataName')]
    public function testReplaceDeclaredPrimitiveRuleWinsOverStorageKeyShape(mixed $storedValue, array $expectedProperties, array $expectedDrops): void
    {
        // count is a declared integer primitive of Sw:New AND the default wires it as an entity storage key (config
        // property "count"). The declared-primitive rule runs first, so the entity branch's shape check is never
        // consulted; a flipped precedence would carry the string and report the integer dropped.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', $storedValue)->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['count' => new LoaderBinding('entity', ['entity' => 'category', 'property' => 'count'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame($expectedProperties, $this->rawProperties($result->roots[0]));
        static::assertSame($expectedDrops, $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('reports static property values the new type cannot hold via droppedProperties')]
    public function testReplaceReportsDroppedProperties(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperties([
            'headline' => 'Hi',
            'ghost' => 'orphaned-value',
            'count' => 'not-an-int',
        ])->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame(['headline' => 'Hi'], $this->rawProperties($result->roots[0]));
        static::assertSame(['ghost' => 'orphaned-value', 'count' => 'not-an-int'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('reports a non-scalar stored value under a new-type primitive key via droppedProperties')]
    public function testReplaceReportsDroppedNonScalarUnderAPrimitiveKey(): void
    {
        // A list variant under a declared string property is reachable input, not a hypothetical: the draft
        // mutation route decodes through DraftLayoutDecoder and runs MutationPipeline, which never persists, so
        // no DAL write and no PreWriteValidationEvent occur and StoredTreeConstraints — reached only from
        // StoredElementListFieldSerializer::buildConstraints() on an actual write — never runs on this tree.
        // carriesOverPropertiesProvider pins the carry-over half of the same input; this pins the report half,
        // which that provider's two-column shape cannot express.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('headline', ['a', 'b'])->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('headline'));
        static::assertSame(['headline' => ['a', 'b']], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('resets droppedProperties on re-apply so a second run does not accumulate the first run drops')]
    public function testReplaceResetsDroppedPropertiesOnReapply(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));

        $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('ghost', 'first-run')->build()]));
        $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 'second-run')->build()]));

        static::assertSame(['count' => 'second-run'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops and reports a stored value under a resolvedBy storage key whose shape does not match its loader branch')]
    public function testReplaceDropsAndReportsStorageKeyShapeMismatch(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('mediaId', ['not', 'a', 'string'])->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('mediaId'));
        static::assertSame(['mediaId' => ['not', 'a', 'string']], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops and reports a stored value under a resolvedBy storage key whose loader is not one of the two built-in resolvedBy loaders')]
    public function testReplaceDropsStorageKeyWiredByNonBuiltinLoader(): void
    {
        // carryableStorageKeys() maps only resolves entries whose loader passes ResolvedByLoaderBranch::fromLoaderSource();
        // the "navigation" loader is neither built-in resolvedBy loader, so it contributes no carryable storage key and
        // the correspondingly-named stored value falls through to droppedProperties. The config still names "activeId"
        // via a "property" key so the drop is attributable solely to the loader-source gate: if it regressed to accept
        // "navigation", the stored string would match the entity branch and be carried instead.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('activeId', 'category-1')->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['navigation' => new LoaderBinding('navigation', ['entity' => 'category', 'property' => 'activeId'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('activeId'));
        static::assertSame(['activeId' => 'category-1'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops wiring whose key is absent from the new type and reports it without re-mapping')]
    public function testReplaceDropsAndReportsAbsentWiring(): void
    {
        $requirement = new DataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['legacy' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame(['legacy'], $replace->droppedWiring());
    }

    #[TestDox('reports a dropped context provider and consumer key once each')]
    public function testReplaceReportsDroppedContextWiring(): void
    {
        $definitions = new ContextDefinitions(
            ['legacyProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
            ['legacyConsumer' => new ContextConsumer(ContextType::Single, true)],
        );
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], $definitions)]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $replace->apply($tree);

        static::assertSame(['legacyProvider', 'legacyConsumer'], $replace->droppedWiring());
    }

    #[TestDox('reports a wiring key dropped from two channels only once')]
    public function testReplaceReportsAKeyDroppedFromBothWiringChannelsOnce(): void
    {
        // droppedWiringKeys() at ReplaceElement.php:216 is array_values(array_unique(array_diff($oldKeys,
        // $keptKeys))). Every other dropped-wiring test uses distinct key names per channel, so array_diff never
        // produces a duplicate for array_unique to collapse. Wiring 'legacy' as both a data requirement and a
        // context consumer makes it appear twice in $oldKeys; without array_unique(), droppedWiring() would report
        // it twice.
        $requirement = new DataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $definitions = new ContextDefinitions([], ['legacy' => new ContextConsumer(ContextType::Single, true)]);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['legacy' => $requirement], [], [], $definitions)]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $replace->apply($tree);

        static::assertSame(['legacy'], $replace->droppedWiring());
    }

    #[TestDox('detaches children of a slot absent from the new type into orphaned without re-mapping')]
    public function testReplaceOrphansAbsentSlotChildren(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'legacy' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame([], $result->roots[0]->slots);
        static::assertSame(['child'], array_map(static fn (StoredElement $e): string => $e->id, $replace->orphaned()));
    }

    #[TestDox('reports the replaced element and its kept descendants as affected')]
    public function testReplaceAffectedCoversKeptSubtree(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'content' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $replace->apply($tree);

        static::assertSame(['el', 'child'], $replace->affected());
    }

    #[TestDox('excludes an orphaned slot child from the affected set')]
    public function testReplaceAffectedExcludesOrphanedChildren(): void
    {
        // $this->affected = $this->subtreeIds($replacement) at ReplaceElement.php:95 walks the REPLACEMENT node.
        // testReplaceOrphansAbsentSlotChildren already drops this same 'legacy' child but only asserts orphaned(),
        // never affected(); testReplaceAffectedCoversKeptSubtree uses a slot the new type keeps, so the original
        // node and the replacement are indistinguishable there. Computing affected() over the pre-replace node
        // instead would still include the orphaned 'child'.
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'legacy' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $replace->apply($tree);

        static::assertSame(['el'], $replace->affected());
    }

    #[TestDox('preserves carried wiring for a shared key but still fill-applies the default for a key the carry left unwired')]
    public function testReplacePreservesCarriedWiringButFillAppliesDefaultForUnwiredKey(): void
    {
        $carriedConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);

        $old = StoredElementBuilder::create('Sw:Old', 'el')
            ->withDataRequirement('product', 'entity', $carriedConfig)
            ->withAttributedSpecification('product', 'core:carried-spec')
            ->build();

        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', [
            'product' => new LoaderBinding('entity', ['entity' => 'product', 'property' => 'productId']),
            'gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds']),
        ], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator($newConfig), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply(new StoredTree([$old], 'product'));

        static::assertSame($carriedConfig, $result->roots[0]->dataRequirements['product']->config);
        static::assertSame($newConfig, $result->roots[0]->dataRequirements['gallery']->config);
        static::assertSame('entity_collection', $result->roots[0]->dataRequirements['gallery']->source);
        static::assertSame(['product' => 'core:carried-spec', 'gallery' => 'core:Sw:New'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('seeds the new type primitive default for a key the old element lacked')]
    public function testReplaceSeedsNewTypeDefaultForAbsentKey(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old')]);

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registryWithDefaults())))->apply($tree);

        static::assertSame('Default tagline', $result->roots[0]->property('tagline')?->jsonSerialize());
    }

    #[TestDox('seeds the new type default for a key whose type-incompatible old value was dropped')]
    public function testReplaceSeedsNewTypeDefaultForDroppedIncompatibleKey(): void
    {
        $replace = new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registryWithDefaults()));

        $result = $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 'not-an-int')->build()]));

        static::assertSame(['count' => 'not-an-int'], $this->rawDrops($replace->droppedProperties()));
        static::assertSame(7, $result->roots[0]->property('count')?->jsonSerialize());
    }

    #[TestDox('keeps the attributed specification for a carried wired key and drops it for a wired key the new type no longer has')]
    public function testReplaceKeepsAttributedSpecificationForCarriedKeyAndDropsForAbsentKey(): void
    {
        $old = StoredElementBuilder::create('Sw:Old', 'el')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withDataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->withAttributedSpecification('product', 'spec-product')
            ->withAttributedSpecification('legacy', 'spec-legacy')
            ->build();

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry())))->apply(new StoredTree([$old]));

        static::assertSame(['product' => 'spec-product'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('drops the attributed specification of a carried key that has no data requirement')]
    public function testReplaceDropsAttributedSpecificationOfAKeyWithoutDataRequirement(): void
    {
        $old = StoredElementBuilder::create('Sw:Old', 'el')
            ->withConsumer('product', ContextType::Single)
            ->withAttributedSpecification('product', 'spec-product')
            ->build();

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply(new StoredTree([$old], 'product'));

        static::assertSame([], $result->roots[0]->attributedSpecifications);
        static::assertArrayHasKey('product', $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame([], $replace->droppedWiring());
    }

    #[TestDox('rejects wiring whose key the new type declares as a primitive property and reports the key as dropped')]
    public function testReplaceRejectsWiringUnderADeclaredPrimitiveKey(): void
    {
        // headline is a declared string primitive of Sw:New, so the wiring carry-over must keep only keys
        // with no enforceable type.
        // The key IS present in the new type, so the presence check alone would carry this requirement onto a
        // primitive property; only the enforceableTypes() operand rejects it.
        $requirement = new DataRequirement('headline', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['headline' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame(['headline'], $replace->droppedWiring());
    }

    #[TestDox('drops wiring whose key the new type declares as an all-primitive union and reports the key')]
    public function testReplaceDropsWiringUnderAnAllPrimitiveUnionKey(): void
    {
        // flexible is declared string|integer in Sw:New: a union, so isPrimitive() is false for it, yet every member is
        // enforceable. A check on isPrimitive() alone would carry this requirement; enforceableTypes() drops it.
        $requirement = new DataRequirement('flexible', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['flexible' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame(['flexible'], $replace->droppedWiring());
    }

    #[TestDox('drops an ordinary consumer targeting an all-primitive union on replacement')]
    public function testReplaceDropsConsumerUnderAnAllPrimitiveUnionKey(): void
    {
        $consumer = new ContextConsumer(ContextType::Single, false);
        $old = new StoredElement('el', 'Sw:Old', contextDefinitions: new ContextDefinitions([], ['flexible' => $consumer]));
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));

        $result = $replace->apply(new StoredTree([$old], 'product'));

        static::assertSame([], $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame(['flexible'], $replace->droppedWiring());
    }

    #[TestDox('carries a null stored under a key the new type declares as a primitive')]
    public function testReplaceCarriesANullUnderADeclaredPrimitive(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('headline', null)->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertTrue($result->roots[0]->property('headline')?->isNull());
        static::assertSame([], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestWith(['wide'])]
    #[TestWith([42])]
    #[TestDox('carries $value matching one member of an all-primitive union')]
    public function testReplaceCarriesAValueMatchingAnAllPrimitiveUnion(string|int $value): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('flexible', $value)->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertSame($value, $result->roots[0]->property('flexible')?->jsonSerialize());
        static::assertSame([], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestWith([1.5])]
    #[TestWith([true])]
    #[TestDox('drops $value matching no member of an all-primitive union')]
    public function testReplaceDropsAValueMatchingNoUnionMember(float|bool $value): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('flexible', $value)->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('flexible'));
        static::assertSame(['flexible' => $value], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops a value under a key the new type declares as a bare object')]
    public function testReplaceDropsAValueUnderAnObjectKey(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('payload', ['a' => 1])->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('payload'));
        static::assertSame(['payload' => ['a' => 1]], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops a value under a key the new type declares as a reference, which nothing can vouch for')]
    public function testReplaceDropsAValueUnderAReferenceKey(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('product', 'some-id')->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('product'));
        static::assertSame(['product' => 'some-id'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops a stored value whose storage key comes from a binding config with an invalid non-string property')]
    public function testReplaceDropsStoredValueForInvalidStorageKeyPropertyConfig(): void
    {
        // A LoaderBinding config is untyped, so "property" can hold a list. carryableStorageKeys() registers a
        // storage key only for a string, so this entry contributes none and the stored value falls through to
        // droppedProperties; without the is_string() guard the list would be used as an array offset directly.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('mediaId', 'media-1')->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => ['mediaId']])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)), mappingInspector: $this->mappingInspector($this->registry()));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('mediaId'));
        static::assertSame(['mediaId' => 'media-1'], $this->rawDrops($replace->droppedProperties()));
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
            mappingInspector: $this->mappingInspector($this->registry()),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDefaultAmbiguous('Sw:New', ['core:Sw:New', 'app1:Sw:New']));
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
    }

    #[TestDox('rejects an unregistered new type with a 400')]
    public function testReplaceUnknownNewTypeRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:Ghost', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
    }

    #[TestDox('rejects replacing an element absent from the tree with a 400')]
    public function testReplaceMissingElementRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'ghost', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator(), mappingInspector: $this->mappingInspector($this->registry()));

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
    }

    #[DataProvider('mappingReplacementProvider')]
    public function testReplaceRetainsOnlyMappingsValidForTheNewType(bool $mappable, bool $retained, ?string $rootSource): void
    {
        $registry = $this->mappingReplacementRegistry($mappable);
        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            'product.name' => new MappingCandidate('product.name', 'label', 'description', 'basic', 'string'),
        ]);
        $inspector = new StoredMappingInspector(
            $registry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
        $old = StoredElementBuilder::create('Sw:Old', 'el')
            ->withProperty('text', 'Authored text')
            ->withConsumer('text', ContextType::Single, scope: ConsumerScope::Root, source: MappingSourceReference::fromRootPath('product.name'))
            ->build();
        $replace = new ReplaceElement(
            $registry,
            'el',
            'Sw:New',
            $this->bindingRegistry([]),
            $this->unboundApplicator(),
            $inspector,
        );

        $result = $replace->apply(new StoredTree([$old], $rootSource));

        static::assertSame($retained, isset($result->roots[0]->contextDefinitions->getAllConsumers()['text']));
        static::assertSame($retained ? [] : ['text'], $replace->droppedWiring());
        static::assertSame('Authored text', $result->roots[0]->property('text')?->asString());
        static::assertSame($rootSource, $result->rootSource);
    }

    public function testReplaceDoesNotReportAnInlineTokenProblemAsDroppedConsumerWiring(): void
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('Sw:New')
            ->primitive('text', 'string', inlineMappable: true)
            ->build();
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'Sw:New');
        $registry->method('get')->willReturn($specification);
        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $inspector = new StoredMappingInspector(
            $registry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
        $old = StoredElementBuilder::create('Sw:Old', 'el')->withProperty('text', 'Hello {{map:product.missing}}')->build();
        $replace = new ReplaceElement(
            $registry,
            'el',
            'Sw:New',
            $this->bindingRegistry([]),
            $this->unboundApplicator(),
            $inspector,
        );

        $result = $replace->apply(new StoredTree([$old], 'product'));

        static::assertSame([], $replace->droppedWiring());
        static::assertSame('Hello {{map:product.missing}}', $result->roots[0]->property('text')?->asString());
    }

    /**
     * @return iterable<string, array{bool, bool, string|null}>
     */
    public static function mappingReplacementProvider(): iterable
    {
        yield 'compatible mappable property' => [true, true, 'product'];
        yield 'property no longer mappable' => [false, false, 'product'];
        yield 'unbound draft drops mapping and retains authored content' => [true, false, null];
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
        yield 'list dropped from a string property' => [['headline' => ['a', 'b']], []];
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>, array<string, mixed>}>
     */
    public static function declaredPrimitiveRuleWinsOverStorageKeyShapeProvider(): iterable
    {
        // The entity branch accepts any string, so only the declared-primitive rule can drop this one.
        yield 'a string the declared integer rejects is dropped' => ['some-id-string', [], ['count' => 'some-id-string']];

        // The entity branch accepts a string only, so only the declared-primitive rule can carry this one.
        yield 'an integer the declared integer accepts is carried' => [5, ['count' => 5], []];
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function carriesStorageKeyMatchingLoaderShapeProvider(): iterable
    {
        yield 'entity shape' => ['mediaId', 'media-1', 'entity', 'media-1'];
        yield 'entity collection shape' => ['galleryIds', ['media-1', 'media-2'], 'entity_collection', ['media-1', 'media-2']];
    }

    private function mappingReplacementRegistry(bool $mappable): AbstractContentSystemElementTypeRegistry
    {
        $specs = [
            'Sw:New' => ContentSystemElementTypeSpecificationBuilder::create('Sw:New')
                ->primitive('text', 'string', mappable: $mappable)
                ->build(),
        ];
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawProperties(StoredElement $element): array
    {
        return $this->rawDrops($element->properties());
    }

    /**
     * @param array<string, StoredValue> $values
     *
     * @return array<string, mixed>
     */
    private function rawDrops(array $values): array
    {
        return array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $values);
    }

    private function mappingInspector(AbstractContentSystemElementTypeRegistry $registry): StoredMappingInspector
    {
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([]);

        return new StoredMappingInspector(
            $registry,
            $candidates,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
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
                'flexible' => $this->union(['string', 'integer']),
                'payload' => $this->primitive('object'),
                'product' => $this->reference(),
            ],
            [new SlotSpecification('content', null, [], '')],
        );

        return TestElementTypeRegistry::of(['Sw:New' => $spec]);
    }

    private function registryWithDefaults(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of(['Sw:New' => ContentSystemElementTypeSpecificationBuilder::create('Sw:New')
            ->primitive('headline', 'string', required: true, default: 'Default headline')
            ->primitive('count', 'integer', required: true, default: 7)
            ->primitive('tagline', 'string', required: true, default: 'Default tagline')
            ->build()]);
    }

    private function primitive(string $type): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, null), false, '', '', null);
    }

    /**
     * @param list<string> $types
     */
    private function union(array $types): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($types, false, null, null), false, '', '', null);
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
