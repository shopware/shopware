<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementMintResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\ResolvedLoaderValue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * `Sw:Tile` declares one property nothing here uses, so every key a test puts on a `Sw:Tile` element
 * reaches the rendered side through exactly one union member and no other.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderedElementFactory::class)]
class RenderedElementFactoryTest extends TestCase
{
    #[TestDox('a key the type declares carries its stored value')]
    public function testDeclaredPropertyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('a data requirement key carries the value its loader resolved')]
    public function testRequirementKeyContributesTheResolvedLoaderValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint($stored, ['product' => $loaded], [], [], []);

        static::assertSame(['product' => $loaded], $rendered->properties);
    }

    #[TestDox('a key context was delivered under carries the delivered value')]
    public function testDeliveredContextKeyContributesTheDeliveredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')->build();

        $rendered = $this->mint($stored, [], ['category' => $delivered], [], []);

        static::assertSame(['category' => $delivered], $rendered->properties);
    }

    /**
     * The ordinary case this tier exists for, and the contrast to
     * {@see testDistributionReferencedKeyNamingADeclaredReferenceIsAbsent}: `data_key` is declared by no
     * type, so it is not a declared reference and its stored value rides through. A predicate that excluded
     * everything but declared primitives would break exactly this.
     */
    #[TestDox('a stored key a parent distribution config names carries its stored value')]
    public function testDistributionReferencedKeyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->build();

        $rendered = $this->mint($stored, [], [], ['data_key'], []);

        static::assertSame(['data_key' => 'left'], $rendered->properties);
    }

    #[TestDox('drops a stored key the element type does not declare')]
    public function testStoredKeyOutsideTheDeclaredSetIsDropped(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->withProperty('internalNote', 'authoring scratch')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('leaves out a consumer key the element declares but nothing delivered')]
    public function testUndeliveredConsumerKeyIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('leaves out a declared key the element stores no value for')]
    public function testDeclaredKeyWithoutAStoredValueIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    /**
     * A reference property's stored value is an id a loader resolves, never something to serve. The declared
     * tier selects primitives, so the id is left to whichever loader claims the key.
     */
    #[TestDox('leaves out a declared reference property holding a stored id')]
    public function testDeclaredReferencePropertyStoredValueIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Product', 'element-1')
            ->withProperty('product', 'product-id')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    /**
     * A union's declared type is an array, so `PropertyType::isPrimitive()` answers false for it. Reading that
     * as "not primitive" excluded an all-primitive-union key from both tiers and its stored value never
     * reached the wire.
     */
    #[TestDox('a key declared as an all-primitive union carries its stored value')]
    public function testAllPrimitiveUnionPropertyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Grid', 'element-1')
            ->withProperty('columns', 3)
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame(['columns' => 3], $rendered->properties);
    }

    /**
     * The other tier. `isDeclaredReference()` gates the distribution-referenced member, and reading an
     * all-primitive union as a reference excluded the key there too — a second exclusion the declared tier's
     * own test cannot reach.
     */
    #[TestDox('a distribution referenced key declared as an all-primitive union carries its stored value')]
    public function testAllPrimitiveUnionDistributionReferencedKeyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Grid', 'element-1')
            ->withProperty('columns', 3)
            ->build();

        $rendered = $this->mint($stored, [], [], ['columns'], []);

        static::assertSame(['columns' => 3], $rendered->properties);
    }

    #[TestDox('leaves out a union key carrying a non-primitive member')]
    public function testUnionWithANonPrimitiveMemberStaysExcluded(): void
    {
        $stored = StoredElementBuilder::create('Sw:Grid', 'element-1')
            ->withProperty('anything', 'stub-id')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('leaves out a declared single-reference property on a type that also declares a union')]
    public function testDeclaredReferenceStaysExcludedBesideAUnion(): void
    {
        $stored = StoredElementBuilder::create('Sw:Grid', 'element-1')
            ->withProperty('product', 'product-id')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    /**
     * `KeyedDistributionConfig::buildConstraints()` checks only that `keyProperty` is a non-blank string, so
     * nothing upstream stops it naming a declared reference property. This tier excludes that key rather
     * than carrying the raw id the invariant keeps off the rendered side.
     */
    #[TestDox('leaves out a distribution referenced key that names a declared reference property')]
    public function testDistributionReferencedKeyNamingADeclaredReferenceIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Product', 'element-1')
            ->withProperty('product', 'product-id')
            ->build();

        $rendered = $this->mint($stored, [], [], ['product'], []);

        static::assertSame([], $rendered->properties);
    }

    /**
     * A rendered null has one producer, a loader that ran and found nothing. An authored null is no value,
     * so the declared tier drops the key instead of rendering the null it holds.
     */
    #[TestDox('leaves out a declared key whose stored value is an authored null')]
    public function testDeclaredKeyHoldingAnAuthoredNullIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', null)
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('leaves out a distribution referenced key whose stored value is an authored null')]
    public function testDistributionReferencedKeyHoldingAnAuthoredNullIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', null)
            ->build();

        $rendered = $this->mint($stored, [], [], ['data_key'], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('a loader that found nothing yields an explicit null where an authored null yields no key at all')]
    public function testLoaderNullIsPresentWhereAnAuthoredNullIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', null)
            ->withProperty('product', null)
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint($stored, ['product' => null], [], [], []);

        static::assertSame(['product' => null], $rendered->properties);
    }

    #[TestDox('delivered context outranks a loader that resolved the same key')]
    public function testDeliveredContextBeatsTheLoaderResolvedValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint(
            $stored,
            ['product' => new StubStruct()],
            ['product' => $delivered],
            [],
            []
        );

        static::assertSame(['product' => $delivered], $rendered->properties);
    }

    #[TestDox('records the producing member of every key it writes')]
    public function testMintRecordsProvenancePerMember(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->withProperty('data_key', 'grouping')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $result = $this->mintResult(
            $stored,
            ['product' => new StubStruct()],
            ['delivered' => new StubStruct()],
            ['data_key'],
            []
        );

        static::assertSame(
            [
                'data_key' => ValueOrigin::DistributionReferenced,
                'headline' => ValueOrigin::DeclaredPrimitive,
                'product' => ValueOrigin::LoaderResolved,
                'delivered' => ValueOrigin::DeliveredContext,
            ],
            array_map(static fn (ValueProvenance $entry): ValueOrigin => $entry->origin, $result->provenance),
        );
    }

    #[TestDox('files a contested key under the member that won it, not the one that wrote it first')]
    public function testProvenanceOfAContestedKeyNamesTheWinningMember(): void
    {
        // Every member writes `headline`, so a factory whose tier write order shifted would keep serving a
        // plausible value while filing the key under the wrong category — the failure this asserts against.
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->withDataRequirement('headline', 'headline', new StubLoaderConfig())
            ->build();

        $result = $this->mintResult(
            $stored,
            ['headline' => new StubStruct()],
            ['headline' => $delivered],
            ['headline'],
            []
        );

        static::assertSame($delivered, $result->element->properties['headline']);
        static::assertSame(ValueOrigin::DeliveredContext, $result->provenance['headline']->origin);
    }

    #[TestDox('files a loader-resolved key with the identity its value dedups by, and no other key with one')]
    public function testProvenanceCarriesTheLoaderIdentityOnlyForLoaderResolvedKeys(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $result = $this->mintResult($stored, ['product' => $loaded], [], [], []);

        $identity = $result->provenance['product']->loaderIdentity;
        static::assertNotNull($identity);
        static::assertSame('stub', $identity->source);
        // The producer's fingerprint describes the value the loader returned, which is what lets the index
        // tell that value apart from one a finalization listener put in its place.
        static::assertSame((new ValueFingerprinter())->fingerprint($loaded), $identity->producedFingerprint);

        static::assertNull($result->provenance['headline']->loaderIdentity);
    }

    #[TestDox('records nothing for a key no member wrote')]
    public function testProvenanceOmitsAnAbsentKey(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('undeclared', 'dropped')
            ->build();

        $result = $this->mintResult($stored, [], [], [], []);

        static::assertSame([], $result->element->properties);
        static::assertSame([], $result->provenance);
    }

    #[TestDox('a loader resolved value outranks the stored value of the same declared key')]
    public function testLoaderResolvedValueBeatsTheDeclaredStoredValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->withDataRequirement('headline', 'headline', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint($stored, ['headline' => $loaded], [], [], []);

        static::assertSame(['headline' => $loaded], $rendered->properties);
    }

    #[TestDox('delivered context outranks the stored value of the same declared key')]
    public function testDeliveredContextBeatsTheDeclaredStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->build();

        $rendered = $this->mint($stored, [], ['headline' => $delivered], [], []);

        static::assertSame(['headline' => $delivered], $rendered->properties);
    }

    #[TestDox('delivered context outranks the stored value of the same distribution referenced key')]
    public function testDeliveredContextBeatsTheDistributionReferencedStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->build();

        $rendered = $this->mint($stored, [], ['data_key' => $delivered], ['data_key'], []);

        static::assertSame(['data_key' => $delivered], $rendered->properties);
    }

    #[TestDox('a loader resolved value outranks the stored value of the same distribution referenced key')]
    public function testLoaderResolvedValueBeatsTheDistributionReferencedStoredValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->withDataRequirement('data_key', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint($stored, ['data_key' => $loaded], [], ['data_key'], []);

        static::assertSame(['data_key' => $loaded], $rendered->properties);
    }

    /**
     * The lowest two tiers cannot disagree: both copy the same stored value under the same key, so the
     * observable contract is that a key claimed by both still carries that one value exactly once.
     */
    #[TestDox('a key that is both declared and distribution referenced carries its stored value once')]
    public function testDeclaredAndDistributionReferencedAgreeOnTheStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], ['headline'], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('a loader that found nothing yields an explicit null while an undelivered consumer key stays absent')]
    public function testLoaderNullIsPresentWhereAnUndeliveredConsumerKeyIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $rendered = $this->mint($stored, ['product' => null], [], [], []);

        static::assertSame(['product' => null], $rendered->properties);
    }

    #[TestDox('a component naming no registered type declares nothing instead of failing')]
    public function testUnregisteredComponentDeclaresNothing(): void
    {
        $stored = StoredElementBuilder::create('Sw:Unregistered', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('unwraps a stored value at every depth, map variants nested in list variants included')]
    public function testStoredValuesAreUnwrappedAtEveryDepth(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('items', ['first', ['label' => 'second', 'tags' => ['a', 'b']]])
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame(
            ['items' => ['first', ['label' => 'second', 'tags' => ['a', 'b']]]],
            $rendered->properties
        );
    }

    #[TestDox('createStructural yields no properties while keeping id, component, style and slots')]
    public function testCreateStructuralKeepsEverythingButTheProperties(): void
    {
        $style = new ElementStyle(['col-span' => 6]);
        $child = new RenderedElement('child-1', 'Sw:Text');
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->withStyle($style)
            ->build();

        $rendered = $this->factory()->createStructural($stored, ['main' => [$child]])->element;

        static::assertSame([], $rendered->properties);
        static::assertSame('element-1', $rendered->id);
        static::assertSame('Sw:Text', $rendered->component);
        static::assertSame($style, $rendered->style);
        static::assertSame(['main' => [$child]], $rendered->slots);
    }

    #[TestDox('carries the id through and hands the slot map over with its names and child order intact')]
    public function testIdAndSlotOrderingSurvive(): void
    {
        $first = new RenderedElement('child-1', 'Sw:Text');
        $second = new RenderedElement('child-2', 'Sw:Text');
        $aside = new RenderedElement('child-3', 'Sw:Text');
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')->build();

        $rendered = $this->mint($stored, [], [], [], [
            'main' => [$first, $second],
            'sidebar' => [$aside],
        ]);

        static::assertSame('element-1', $rendered->id);
        static::assertSame(['main' => [$first, $second], 'sidebar' => [$aside]], $rendered->slots);
    }

    /**
     * The rendered element alone, for every test about which keys survive. Raw loader values are wrapped in
     * their identity here so those tests keep saying what they are about.
     *
     * @param array<string, mixed> $loaderValues
     * @param array<string, mixed> $delivered
     * @param list<string> $distributionKeys
     * @param array<string, list<RenderedElement>> $slots
     */
    private function mint(
        StoredElement $stored,
        array $loaderValues,
        array $delivered,
        array $distributionKeys,
        array $slots,
    ): RenderedElement {
        return $this->mintResult($stored, $loaderValues, $delivered, $distributionKeys, $slots)->element;
    }

    /**
     * @param array<string, mixed> $loaderValues
     * @param array<string, mixed> $delivered
     * @param list<string> $distributionKeys
     * @param array<string, list<RenderedElement>> $slots
     */
    private function mintResult(
        StoredElement $stored,
        array $loaderValues,
        array $delivered,
        array $distributionKeys,
        array $slots,
    ): ElementMintResult {
        $fingerprinter = new ValueFingerprinter();

        $resolved = array_map(
            static fn (mixed $value): ResolvedLoaderValue => new ResolvedLoaderValue(
                $value,
                // A faithful identity: the fingerprint is taken from the value the way the real producer takes
                // it, so a test comparing it against a recomputed one compares the same rule.
                new LoaderValueIdentity('stub', 'config-hash', 'inputs-hash', $fingerprinter->fingerprint($value)),
            ),
            $loaderValues
        );

        return $this->factory()->create($stored, $resolved, $delivered, $distributionKeys, $slots);
    }

    private function factory(): RenderedElementFactory
    {
        $specs = [
            'Sw:Text' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
                ->primitive('headline', 'string')
                ->primitive('items', 'string')
                ->reference('product', StubStruct::class)
                ->build(),
            'Sw:Product' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Product')
                ->reference('product', StubStruct::class)
                ->build(),
            'Sw:Tile' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Tile')
                ->primitive('label', 'string')
                ->build(),
            'Sw:Grid' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Grid')
                ->union('columns', ['integer', 'string'])
                ->union('anything', ['string', StubStruct::class])
                ->reference('product', StubStruct::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return new RenderedElementFactory($registry);
    }
}
