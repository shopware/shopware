<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[TestDox('carries its stored value for declared keys')]
    public function testDeclaredPropertyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('carries the value a data requirement key\'s loader resolved')]
    public function testRequirementKeyContributesTheResolvedLoaderValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->mint($stored, ['product' => $loaded], [], [], []);

        static::assertSame(['product' => $loaded], $rendered->properties);
    }

    #[TestDox('carries the value delivered under a context key')]
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
    #[TestDox('carries its stored value for distribution-referenced keys')]
    public function testDistributionReferencedKeyContributesItsStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->build();

        $rendered = $this->mint($stored, [], [], ['data_key'], []);

        static::assertSame(['data_key' => 'left'], $rendered->properties);
    }

    /**
     * Only a single class string names something a loader or a context delivery can fill. Every other
     * declaration is authored, so its stored value is what serving means — the same split
     * `ElementResolver::resolve()` makes when it files a declaration under `PropertyKind::Primitive`.
     *
     * @param string|list<string> $declaredType
     */
    #[DataProvider('authoredDeclarationProvider')]
    #[TestDox('carries its stored value for a key declared as $_dataName')]
    public function testAuthoredDeclarationContributesItsStoredValue(string|array $declaredType, mixed $value): void
    {
        $stored = StoredElementBuilder::create('Sw:Declarations', 'element-1')
            ->withProperty('subject', $value)
            ->build();

        $rendered = $this->mint($stored, [], [], [], [], $declaredType);

        static::assertSame(['subject' => $value], $rendered->properties);
    }

    /**
     * The same table on the other tier. `isDeclaredReference()` gates the distribution-referenced member, so
     * every declaration the declared tier serves this one must serve too — an agreement neither tier's own
     * test can establish alone. The exclusion half of the split is covered per tier by
     * {@see testDeclaredReferencePropertyStoredValueIsAbsent} and
     * {@see testDistributionReferencedKeyNamingADeclaredReferenceIsAbsent}.
     *
     * @param string|list<string> $declaredType
     */
    #[DataProvider('authoredDeclarationProvider')]
    #[TestDox('carries its stored value for a distribution referenced key declared as $_dataName')]
    public function testAuthoredDeclarationContributesItsStoredValueWhenDistributionReferenced(
        string|array $declaredType,
        mixed $value,
    ): void {
        $stored = StoredElementBuilder::create('Sw:Declarations', 'element-1')
            ->withProperty('subject', $value)
            ->build();

        $rendered = $this->mint($stored, [], [], ['subject'], [], $declaredType);

        static::assertSame(['subject' => $value], $rendered->properties);
    }

    #[TestDox('prefers a loader resolved value over the stored value of the same declared key')]
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

    #[TestDox('prefers delivered context over the stored value of the same declared key')]
    public function testDeliveredContextBeatsTheDeclaredStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->build();

        $rendered = $this->mint($stored, [], ['headline' => $delivered], [], []);

        static::assertSame(['headline' => $delivered], $rendered->properties);
    }

    #[TestDox('prefers delivered context over a loader that resolved the same key')]
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

    #[TestDox('prefers a loader resolved value over the stored value of the same distribution referenced key')]
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

    #[TestDox('prefers delivered context over the stored value of the same distribution referenced key')]
    public function testDeliveredContextBeatsTheDistributionReferencedStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->build();

        $rendered = $this->mint($stored, [], ['data_key' => $delivered], ['data_key'], []);

        static::assertSame(['data_key' => $delivered], $rendered->properties);
    }

    /**
     * The lowest two tiers cannot disagree: both copy the same stored value under the same key, so the
     * observable contract is that a key claimed by both still carries that one value exactly once.
     */
    #[TestDox('carries stored value once when both declared and distribution referenced')]
    public function testDeclaredAndDistributionReferencedAgreeOnTheStoredValue(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], ['headline'], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
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

    #[TestDox('yields an explicit null for a loader that found nothing and no key at all for an authored null')]
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

    #[TestDox('yields an explicit null for a loader that found nothing while an undelivered consumer key stays absent')]
    public function testLoaderNullIsPresentWhereAnUndeliveredConsumerKeyIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $rendered = $this->mint($stored, ['product' => null], [], [], []);

        static::assertSame(['product' => null], $rendered->properties);
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

    #[TestDox('declares nothing for unregistered component types')]
    public function testUnregisteredComponentDeclaresNothing(): void
    {
        $stored = StoredElementBuilder::create('Sw:Unregistered', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->mint($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
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
                'headline' => ValueOrigin::DeclaredAuthored,
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

    #[TestDox('yields structure with id, component, style and slots but no properties')]
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

    /**
     * @return iterable<string, array{string|list<string>, mixed}>
     */
    public static function authoredDeclarationProvider(): iterable
    {
        yield 'a single primitive' => ['integer', 3];

        yield 'an all-primitive union' => [['integer', 'string'], 3];

        // The shipped grid keys: `columns`/`rows` are integer|object, `padding`/`margin` are string|object.
        yield 'a mixed union carrying object' => [['integer', 'object'], 3];

        yield 'a mixed union carrying an FQCN' => [['string', StubStruct::class], 'left'];

        yield 'a bare object' => ['object', ['nested' => 'value']];
    }

    /**
     * The rendered element alone, for every test about which keys survive. Raw loader values are wrapped in
     * their identity here so those tests keep saying what they are about.
     *
     * @param array<string, mixed> $loaderValues
     * @param array<string, mixed> $delivered
     * @param list<string> $distributionKeys
     * @param array<string, list<RenderedElement>> $slots
     * @param string|list<string>|null $subjectType the declared type of `Sw:Declarations`' `subject` property
     */
    private function mint(
        StoredElement $stored,
        array $loaderValues,
        array $delivered,
        array $distributionKeys,
        array $slots,
        string|array|null $subjectType = null,
    ): RenderedElement {
        return $this->mintResult($stored, $loaderValues, $delivered, $distributionKeys, $slots, $subjectType)->element;
    }

    /**
     * @param array<string, mixed> $loaderValues
     * @param array<string, mixed> $delivered
     * @param list<string> $distributionKeys
     * @param array<string, list<RenderedElement>> $slots
     * @param string|list<string>|null $subjectType
     */
    private function mintResult(
        StoredElement $stored,
        array $loaderValues,
        array $delivered,
        array $distributionKeys,
        array $slots,
        string|array|null $subjectType = null,
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

        return $this->factory($subjectType)->create($stored, $resolved, $delivered, $distributionKeys, $slots);
    }

    /**
     * @param string|list<string>|null $subjectType
     */
    private function factory(string|array|null $subjectType = null): RenderedElementFactory
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
            // One property whose declared type each test supplies, so a test varies the declaration itself
            // rather than picking from a fixed menu of them.
            'Sw:Declarations' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Declarations')
                ->declared('subject', $subjectType ?? 'string')
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
