<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
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

        $rendered = $this->factory()->create($stored, [], [], [], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('a data requirement key carries the value its loader resolved')]
    public function testRequirementKeyContributesTheResolvedLoaderValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->factory()->create($stored, ['product' => $loaded], [], [], []);

        static::assertSame(['product' => $loaded], $rendered->properties);
    }

    #[TestDox('a key context was delivered under carries the delivered value')]
    public function testDeliveredContextKeyContributesTheDeliveredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')->build();

        $rendered = $this->factory()->create($stored, [], ['category' => $delivered], [], []);

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

        $rendered = $this->factory()->create($stored, [], [], ['data_key'], []);

        static::assertSame(['data_key' => 'left'], $rendered->properties);
    }

    #[TestDox('drops a stored key the element type does not declare')]
    public function testStoredKeyOutsideTheDeclaredSetIsDropped(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Hello')
            ->withProperty('internalNote', 'authoring scratch')
            ->build();

        $rendered = $this->factory()->create($stored, [], [], [], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('leaves out a consumer key the element declares but nothing delivered')]
    public function testUndeliveredConsumerKeyIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $rendered = $this->factory()->create($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('leaves out a declared key the element stores no value for')]
    public function testDeclaredKeyWithoutAStoredValueIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')->build();

        $rendered = $this->factory()->create($stored, [], [], [], []);

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

        $rendered = $this->factory()->create($stored, [], [], [], []);

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

        $rendered = $this->factory()->create($stored, [], [], ['product'], []);

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

        $rendered = $this->factory()->create($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('leaves out a distribution referenced key whose stored value is an authored null')]
    public function testDistributionReferencedKeyHoldingAnAuthoredNullIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', null)
            ->build();

        $rendered = $this->factory()->create($stored, [], [], ['data_key'], []);

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

        $rendered = $this->factory()->create($stored, ['product' => null], [], [], []);

        static::assertSame(['product' => null], $rendered->properties);
    }

    #[TestDox('delivered context outranks a loader that resolved the same key')]
    public function testDeliveredContextBeatsTheLoaderResolvedValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $rendered = $this->factory()->create(
            $stored,
            ['product' => new StubStruct()],
            ['product' => $delivered],
            [],
            []
        );

        static::assertSame(['product' => $delivered], $rendered->properties);
    }

    #[TestDox('a loader resolved value outranks the stored value of the same declared key')]
    public function testLoaderResolvedValueBeatsTheDeclaredStoredValue(): void
    {
        $loaded = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->withDataRequirement('headline', 'headline', new StubLoaderConfig())
            ->build();

        $rendered = $this->factory()->create($stored, ['headline' => $loaded], [], [], []);

        static::assertSame(['headline' => $loaded], $rendered->properties);
    }

    #[TestDox('delivered context outranks the stored value of the same declared key')]
    public function testDeliveredContextBeatsTheDeclaredStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('headline', 'Authored')
            ->build();

        $rendered = $this->factory()->create($stored, [], ['headline' => $delivered], [], []);

        static::assertSame(['headline' => $delivered], $rendered->properties);
    }

    #[TestDox('delivered context outranks the stored value of the same distribution referenced key')]
    public function testDeliveredContextBeatsTheDistributionReferencedStoredValue(): void
    {
        $delivered = new StubStruct();
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('data_key', 'left')
            ->build();

        $rendered = $this->factory()->create($stored, [], ['data_key' => $delivered], ['data_key'], []);

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

        $rendered = $this->factory()->create($stored, ['data_key' => $loaded], [], ['data_key'], []);

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

        $rendered = $this->factory()->create($stored, [], [], ['headline'], []);

        static::assertSame(['headline' => 'Hello'], $rendered->properties);
    }

    #[TestDox('a loader that found nothing yields an explicit null while an undelivered consumer key stays absent')]
    public function testLoaderNullIsPresentWhereAnUndeliveredConsumerKeyIsAbsent(): void
    {
        $stored = StoredElementBuilder::create('Sw:Tile', 'element-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->withConsumer('category', ContextType::Single, required: true)
            ->build();

        $rendered = $this->factory()->create($stored, ['product' => null], [], [], []);

        static::assertSame(['product' => null], $rendered->properties);
    }

    #[TestDox('a component naming no registered type declares nothing instead of failing')]
    public function testUnregisteredComponentDeclaresNothing(): void
    {
        $stored = StoredElementBuilder::create('Sw:Unregistered', 'element-1')
            ->withProperty('headline', 'Hello')
            ->build();

        $rendered = $this->factory()->create($stored, [], [], [], []);

        static::assertSame([], $rendered->properties);
    }

    #[TestDox('unwraps a stored value at every depth, map variants nested in list variants included')]
    public function testStoredValuesAreUnwrappedAtEveryDepth(): void
    {
        $stored = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('items', ['first', ['label' => 'second', 'tags' => ['a', 'b']]])
            ->build();

        $rendered = $this->factory()->create($stored, [], [], [], []);

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

        $rendered = $this->factory()->createStructural($stored, ['main' => [$child]]);

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

        $rendered = $this->factory()->create($stored, [], [], [], [
            'main' => [$first, $second],
            'sidebar' => [$aside],
        ]);

        static::assertSame('element-1', $rendered->id);
        static::assertSame(['main' => [$first, $second], 'sidebar' => [$aside]], $rendered->slots);
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
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return new RenderedElementFactory($registry);
    }
}
