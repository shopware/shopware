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
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReplaceElement::class)]
class ReplaceElementTest extends TestCase
{
    #[TestDox('keeps the element id while swapping the component')]
    public function testReplaceKeepsElementId(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old')]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('el', $result->roots[0]->id);
        static::assertSame('Sw:New', $result->roots[0]->component);
    }

    #[TestDox('keeps wiring whose key matches a new-type reference property and does not report it as dropped')]
    public function testReplaceKeepsMatchingWiring(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['product' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
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

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('child', $result->roots[0]->slots['content'][0]->id);
    }

    #[TestDox('keeps context definitions whose key matches a new-type reference property and drops the rest')]
    public function testReplaceContextDefinitionsCarryover(): void
    {
        $kept = new ContextConsumer(ContextType::Single, true);
        $dropped = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $definitions = new ContextDefinitions(['legacy' => $dropped], ['product' => $kept]);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], $definitions)]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame(['product' => $kept], $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame([], $result->roots[0]->contextDefinitions->getAllProviders());
    }

    #[TestDox('carries the element style over to the replacement unconditionally on a type swap')]
    public function testReplaceCarriesStyleUnconditionally(): void
    {
        $style = new ElementStyle(['col-span' => ['md' => 6], 'display' => ['xs' => false]]);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], new ContextDefinitions([], []), $style)]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

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

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame($expectedKept, $this->rawProperties($result->roots[0]));
    }

    #[TestDox('does not overwrite a carried-over authored value with the new type default')]
    public function testReplaceKeepsAuthoredValueOverNewTypeDefault(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('headline', 'Authored')->build()]);

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('Authored', $result->roots[0]->property('headline')?->jsonSerialize());
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

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator($newConfig));
        $result = $replace->apply(new StoredTree([$old]));

        static::assertSame($carriedConfig, $result->roots[0]->dataRequirements['product']->config);
        static::assertSame($newConfig, $result->roots[0]->dataRequirements['gallery']->config);
        static::assertSame('entity_collection', $result->roots[0]->dataRequirements['gallery']->source);
        static::assertSame(['product' => 'core:carried-spec', 'gallery' => 'core:Sw:New'], $result->roots[0]->attributedSpecifications);
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

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class))))->apply($tree);

        static::assertSame($expectedValue, $result->roots[0]->property($propertyKey)?->jsonSerialize());
    }

    #[TestDox('seeds the new type primitive default for a key the old element lacked')]
    public function testReplaceSeedsNewTypeDefaultForAbsentKey(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old')]);

        $result = (new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame('Default tagline', $result->roots[0]->property('tagline')?->jsonSerialize());
    }

    #[TestDox('seeds the new type default for a key whose type-incompatible old value was dropped')]
    public function testReplaceSeedsNewTypeDefaultForDroppedIncompatibleKey(): void
    {
        $replace = new ReplaceElement($this->registryWithDefaults(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $result = $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 'not-an-int')->build()]));

        static::assertSame(['count' => 'not-an-int'], $this->rawDrops($replace->droppedProperties()));
        static::assertSame(7, $result->roots[0]->property('count')?->jsonSerialize());
    }

    #[TestDox('does not throw and applies no additional wiring when the new type has no default specification')]
    public function testReplaceWithNoDefaultAppliesNothingExtra(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old')]);

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply($tree);

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame([], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('applies the declared-primitive rule, not the storage-key shape check, for a key that is both, dropping a value the primitive type rejects')]
    public function testReplaceDeclaredPrimitiveRuleWinsDroppingTypeMismatchOverStorageKeyShape(): void
    {
        // count is a declared integer primitive of Sw:New AND the default wires it as an entity storage key (config
        // property "count"). The declared-primitive rule runs first and rejects the string, so the entity branch's
        // shape check (which accepts any string) is never consulted; a flipped precedence would carry the string.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 'some-id-string')->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['count' => new LoaderBinding('entity', ['entity' => 'category', 'property' => 'count'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('count'));
        static::assertSame(['count' => 'some-id-string'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('applies the declared-primitive rule, not the storage-key shape check, for a key that is both, carrying a value the primitive type accepts')]
    public function testReplaceDeclaredPrimitiveRuleWinsCarryingTypeMatchOverStorageKeyShape(): void
    {
        // count is a declared integer primitive and also wired as an entity storage key. The declared-primitive rule
        // carries the matching integer; the entity branch's shape check (a string only) would have dropped it, so a
        // flipped precedence would report it dropped instead.
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 5)->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['count' => new LoaderBinding('entity', ['entity' => 'category', 'property' => 'count'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertSame(5, $result->roots[0]->property('count')?->jsonSerialize());
        static::assertSame([], $replace->droppedProperties());
    }

    #[TestDox('reports static property values the new type cannot hold via droppedProperties')]
    public function testReplaceReportsDroppedProperties(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperties([
            'headline' => 'Hi',
            'ghost' => 'orphaned-value',
            'count' => 'not-an-int',
        ])->build()]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
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

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('headline'));
        static::assertSame(['headline' => ['a', 'b']], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('resets droppedProperties on re-apply so a second run does not accumulate the first run drops')]
    public function testReplaceResetsDroppedPropertiesOnReapply(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('ghost', 'first-run')->build()]));
        $replace->apply(new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('count', 'second-run')->build()]));

        static::assertSame(['count' => 'second-run'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops and reports a stored value under a resolvedBy storage key whose shape does not match its loader branch')]
    public function testReplaceDropsAndReportsStorageKeyShapeMismatch(): void
    {
        $tree = new StoredTree([StoredElementBuilder::create('Sw:Old', 'el')->withProperty('mediaId', ['not', 'a', 'string'])->build()]);
        $default = new BindingSpecification('Sw:New', 'Sw:New', 'New', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])], [], 'core');

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
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

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry(['core:Sw:New' => $default]), $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));
        $result = $replace->apply($tree);

        static::assertNull($result->roots[0]->property('activeId'));
        static::assertSame(['activeId' => 'category-1'], $this->rawDrops($replace->droppedProperties()));
    }

    #[TestDox('drops wiring whose key is absent from the new type and reports it without re-mapping')]
    public function testReplaceDropsAndReportsAbsentWiring(): void
    {
        $requirement = new DataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', ['legacy' => $requirement])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $replace->apply($tree);

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame(['legacy'], $replace->droppedWiring());
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

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator()))->apply(new StoredTree([$old]));

        static::assertSame(['product' => 'spec-product'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('reports a dropped context provider and consumer key once each')]
    public function testReplaceReportsDroppedContextWiring(): void
    {
        $definitions = new ContextDefinitions(
            ['legacyProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
            ['legacyConsumer' => new ContextConsumer(ContextType::Single, true)],
        );
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [], $definitions)]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $replace->apply($tree);

        static::assertSame(['legacyProvider', 'legacyConsumer'], $replace->droppedWiring());
    }

    #[TestDox('detaches children of a slot absent from the new type into orphaned without re-mapping')]
    public function testReplaceOrphansAbsentSlotChildren(): void
    {
        $tree = new StoredTree([new StoredElement('el', 'Sw:Old', [], [], [
            'legacy' => [new StoredElement('child', 'Sw:Block')],
        ])]);

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
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

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());
        $replace->apply($tree);

        static::assertSame(['el', 'child'], $replace->affected());
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
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
    }

    #[TestDox('rejects an unregistered new type with a 400')]
    public function testReplaceUnknownNewTypeRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:Ghost', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
    }

    #[TestDox('rejects replacing an element absent from the tree with a 400')]
    public function testReplaceMissingElementRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'ghost', 'Sw:New', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $replace->apply(new StoredTree([new StoredElement('el', 'Sw:Old')]));
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
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function carriesStorageKeyMatchingLoaderShapeProvider(): iterable
    {
        yield 'entity shape' => ['mediaId', 'media-1', 'entity', 'media-1'];
        yield 'entity collection shape' => ['galleryIds', ['media-1', 'media-2'], 'entity_collection', ['media-1', 'media-2']];
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
