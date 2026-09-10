<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UpdateElementProperties;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateElementProperties::class)]
class UpdateElementPropertiesTest extends TestCase
{
    private const TYPE = 'Sw:Test:Updatable';

    #[TestDox('replaces one property value and carries every other key verbatim, the undeclared storage key included')]
    public function testReplacesOneValueAndCarriesTheRest(): void
    {
        $tree = new StoredTree([$this->target()]);

        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], []))->apply($tree);

        $properties = $this->propertiesOf($result, 'block-a');
        static::assertSame('New', $properties['headline']);
        static::assertSame('h1', $properties['tag']);
        static::assertSame('m-1', $properties['mediaId']);
        static::assertSame([Defaults::LANGUAGE_SYSTEM => 'Autumn sale'], $properties['label']);
    }

    #[TestDox('creates a declared property key on an element that does not carry it yet')]
    public function testCreatesAnAbsentKey(): void
    {
        $element = StoredElementBuilder::create(self::TYPE, 'block-a')->build();

        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['columns' => 3], []))->apply(new StoredTree([$element]));

        static::assertSame(3, $this->propertiesOf($result, 'block-a')['columns']);
    }

    #[TestDox('writes a translatable property\'s language map exactly as supplied')]
    public function testWritesALanguageMapAsSupplied(): void
    {
        $german = Uuid::randomHex();
        $map = [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', $german => 'Herbstschlussverkauf'];

        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['label' => $map], []))->apply(new StoredTree([$this->target()]));

        static::assertSame($map, $this->propertiesOf($result, 'block-a')['label']);
    }

    #[TestDox('writes a present null under a non-translatable primitive')]
    public function testWritesAPresentNull(): void
    {
        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['headline' => null], []))->apply(new StoredTree([$this->target()]));

        $stored = $this->elementOf($result, 'block-a')->property('headline');
        static::assertNotNull($stored, 'the key is present, carrying the null variant');
        static::assertTrue($stored->isNull());
    }

    #[TestDox('drops exactly the named property key and leaves every other key in place')]
    public function testRemovesAKey(): void
    {
        $result = (new UpdateElementProperties($this->registry(), 'block-a', [], ['headline']))->apply(new StoredTree([$this->target()]));

        static::assertSame(['tag', 'label', 'mediaId'], array_keys($this->propertiesOf($result, 'block-a')));
    }

    #[TestDox('leaves a removed key with a declared type default absent instead of reseeding it')]
    public function testRemovedDefaultedKeyStaysAbsent(): void
    {
        $result = (new UpdateElementProperties($this->registry(), 'block-a', [], ['tag']))->apply(new StoredTree([$this->target()]));

        static::assertArrayNotHasKey('tag', $this->propertiesOf($result, 'block-a'));
    }

    #[TestDox('clears a translatable property by dropping its key outright, never by leaving an empty language map behind')]
    public function testRemovesATranslatableKey(): void
    {
        // The removal gate reads only "declared and primitive", and a translatable property declares `string`,
        // so the op admits it without consulting requiredness; that distinction is drawn above the op.
        $result = (new UpdateElementProperties($this->registry(), 'block-a', [], ['label']))->apply(new StoredTree([$this->target()]));

        static::assertSame(['headline', 'tag', 'mediaId'], array_keys($this->propertiesOf($result, 'block-a')));
    }

    #[TestDox('reports the target as the only affected element and mints nothing')]
    public function testAffectedIsTheTargetAndNothingIsCreated(): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], []);
        $update->apply(new StoredTree([$this->target(), StoredElementBuilder::create(self::TYPE, 'block-b')->build()]));

        static::assertSame(['block-a'], $update->affected());
        static::assertSame([], $update->created());
    }

    #[TestDox('detaches nothing, so the orphan and drop channels stay empty')]
    public function testDetachmentChannelsStayEmpty(): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], ['tag']);
        $update->apply(new StoredTree([$this->target()]));

        static::assertSame([], $update->orphaned());
        static::assertSame([], $update->droppedWiring());
        static::assertSame([], $update->droppedProperties());
    }

    #[TestDox('splices the target in wholesale, so its untouched children stay the identical instances')]
    public function testUntouchedChildrenKeepInstanceIdentity(): void
    {
        $grandchild = StoredElementBuilder::create(self::TYPE, 'block-c')->build();
        $child = StoredElementBuilder::create(self::TYPE, 'block-b')->withSlot('content', [$grandchild])->build();
        $parent = StoredElementBuilder::create(self::TYPE, 'block-a')
            ->withProperty('headline', 'Old')
            ->withSlot('content', [$child])
            ->build();

        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], []))->apply(new StoredTree([$parent]));

        $keptChild = $result->roots[0]->slots['content'][0];
        static::assertSame($child, $keptChild);
        static::assertSame($grandchild, $keptChild->slots['content'][0]);
    }

    #[TestDox('carries an untouched root sibling over with its value intact')]
    public function testUntouchedRootSiblingKeepsItsValue(): void
    {
        $sibling = StoredElementBuilder::create(self::TYPE, 'block-b')->withProperty('headline', 'Sibling')->build();

        $result = (new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], []))
            ->apply(new StoredTree([$this->target(), $sibling]));

        // Instance identity is deliberately unasserted: the module contract permits a result tree to alias an
        // input subtree by reference, so whether the sibling comes back as the same instance or an equal one is
        // not this op's promise to keep. StoredTree::replace()'s own rebuild behaviour is pinned in StoredTreeTest.
        static::assertEquals($sibling, $result->roots[1]);
    }

    #[TestDox('rejects an element id that is not in the tree with a 400')]
    public function testUnknownElementRejected(): void
    {
        $update = new UpdateElementProperties($this->registry(), 'ghost', ['headline' => 'New'], []);

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $update->apply(new StoredTree([$this->target()]));
    }

    #[TestDox('rejects an element whose component is not a registered type with a 400')]
    public function testUnregisteredComponentRejected(): void
    {
        $element = StoredElementBuilder::create('Sw:Test:Ghost', 'block-a')->build();
        $update = new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], []);

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Test:Ghost'));
        $update->apply(new StoredTree([$element]));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    #[DataProvider('undeclaredKeyProvider')]
    #[TestDox('rejects $_dataName with a 400')]
    public function testKeyThatIsNotADeclaredPrimitiveRejected(array $values, array $removeKeys, string $rejectedKey): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', $values, $removeKeys);

        $this->expectExceptionObject(ContentSystemException::mutationPropertyUnknown('block-a', $rejectedKey));
        $update->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, string}>
     */
    public static function undeclaredKeyProvider(): iterable
    {
        yield 'a key the type does not declare at all' => [['ghost' => 'x'], [], 'ghost'];

        // A reference property is wiring, not a value: it is declared, so it passes the presence half of the
        // gate and is refused on the primitive half.
        yield 'a declared reference property as a write target' => [['media' => 'x'], [], 'media'];

        // A resolvedBy storage key is undeclared by design and first-class in storage, so it is carried when
        // unnamed and refused the moment the request names it.
        yield 'a resolvedBy storage key as a write target' => [['mediaId' => 'x'], [], 'mediaId'];

        yield 'an undeclared key in the removal list' => [[], ['ghost'], 'ghost'];

        yield 'a declared reference property in the removal list' => [[], ['media'], 'media'];

        yield 'a resolvedBy storage key in the removal list' => [[], ['mediaId'], 'mediaId'];
    }

    #[TestDox('rejects a key present in both the value map and the removal list with a 400')]
    public function testKeyInBothListsRejected(): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', ['headline' => 'New'], ['headline']);

        $this->expectExceptionObject(ContentSystemException::mutationPropertyConflict('block-a', 'headline'));
        $update->apply(new StoredTree([$this->target()]));
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('rejectedValueProvider')]
    #[TestDox('rejects $_dataName, naming the element, the key and the actual type')]
    public function testValueRejectedByTheDeclaredType(array $values, ContentSystemException $expected): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', $values, []);

        $this->expectExceptionObject($expected);
        $update->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function rejectedValueProvider(): iterable
    {
        yield 'a value its declared type does not admit' => [
            ['columns' => 'three'],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'columns', 'string'),
        ];

        // 'label' is declared `string` and translatable, so this same bare string would be admitted on the
        // non-translatable branch of PropertyType::admits(); only the translatable branch, which requires a
        // non-list array of strings, refuses it.
        yield 'a bare string under a translatable key, which only a language map admits' => [
            ['label' => 'Autumn sale'],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'label', 'string'),
        ];

        // No translations is the key being absent and never an empty map. StoredValue::fromDecoded([]) yields
        // the list variant, since array_is_list([]) is true, and PropertyType::admits() refuses a list on the
        // translatable branch. The reported actual type is get_debug_type([]), 'array' for a list and a map alike.
        yield 'an empty language map under a translatable key' => [
            ['label' => []],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'label', 'array'),
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    #[DataProvider('ruleOrderProvider')]
    #[TestDox('reports $_dataName')]
    public function testFirstFailingRuleReports(array $values, array $removeKeys, ContentSystemException $expected): void
    {
        $update = new UpdateElementProperties($this->registry(), 'block-a', $values, $removeKeys);

        $this->expectExceptionObject($expected);
        $update->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, ContentSystemException}>
     */
    public static function ruleOrderProvider(): iterable
    {
        yield 'the unknown key ahead of the conflict it also is' => [
            ['ghost' => 'x'],
            ['ghost'],
            ContentSystemException::mutationPropertyUnknown('block-a', 'ghost'),
        ];

        yield 'the conflict ahead of the value rejection it also is' => [
            ['columns' => 'three'],
            ['columns'],
            ContentSystemException::mutationPropertyConflict('block-a', 'columns'),
        ];

        // The two rules above are broken by the same key, so they cannot tell a per-key evaluation from the
        // spec's per-rule one. Here the failures sit on different keys in different lists: an implementation
        // that judged the value map before scanning the removal list would report the rejection instead.
        yield 'the unknown removal key ahead of a value rejection on another key' => [
            ['columns' => 'three'],
            ['ghost'],
            ContentSystemException::mutationPropertyUnknown('block-a', 'ghost'),
        ];
    }

    private function target(): StoredElement
    {
        return StoredElementBuilder::create(self::TYPE, 'block-a')
            ->withProperty('headline', 'Old')
            ->withProperty('tag', 'h1')
            ->withProperty('label', [Defaults::LANGUAGE_SYSTEM => 'Autumn sale'])
            // undeclared by design: a resolvedBy storage key the op carries while no request names it
            ->withProperty('mediaId', 'm-1')
            ->build();
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of([
            self::TYPE => ContentSystemElementTypeSpecificationBuilder::create(self::TYPE)
                ->primitive('headline', 'string')
                ->primitive('tag', 'string', default: 'h1')
                ->primitive('label', 'string', translatable: true)
                ->primitive('columns', 'integer')
                ->reference('media', StubStruct::class)
                ->build(),
        ]);
    }

    private function elementOf(StoredTree $tree, string $id): StoredElement
    {
        $element = $tree->find($id);
        static::assertInstanceOf(StoredElement::class, $element);

        return $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function propertiesOf(StoredTree $tree, string $id): array
    {
        return array_map(
            static fn (StoredValue $value): mixed => $value->jsonSerialize(),
            $this->elementOf($tree, $id)->properties()
        );
    }
}
