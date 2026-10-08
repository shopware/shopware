<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\TranslateElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslateElement::class)]
class TranslateElementTest extends TestCase
{
    private const TYPE = 'Sw:Test:Translatable';

    /**
     * @param array<string, int|bool> $map
     */
    #[DataProvider('typedLanguageMapProvider')]
    #[TestDox('writes $_dataName exactly as supplied')]
    public function testWritesATypedLanguageMapAsSupplied(string $key, array $map): void
    {
        $result = (new TranslateElement($this->registry(), 'block-a', [$key => $map]))->apply(new StoredTree([$this->target()]));

        static::assertSame($map, $this->propertiesOf($result, 'block-a')[$key]);
    }

    /**
     * @return iterable<string, array{string, array<string, int|bool>}>
     */
    public static function typedLanguageMapProvider(): iterable
    {
        yield 'an integer language map on a translatable integer' => [
            'columns',
            [Defaults::LANGUAGE_SYSTEM => 3, Uuid::fromStringToHex('language-german') => 0],
        ];

        yield 'a boolean language map on a translatable boolean' => [
            'visible',
            [Defaults::LANGUAGE_SYSTEM => true, Uuid::fromStringToHex('language-german') => false],
        ];
    }

    #[TestDox('writes a language map that lacks the anchor entry as supplied, without adding one')]
    public function testWritesAMapWithoutTheAnchorEntry(): void
    {
        $map = [$this->german() => 'Herbstschlussverkauf'];

        $result = (new TranslateElement($this->registry(), 'block-a', ['label' => $map]))->apply(new StoredTree([$this->target()]));

        static::assertSame($map, $this->propertiesOf($result, 'block-a')['label']);
    }

    #[TestDox('writes the language map of every key in one request, each as supplied, and carries the keys not named')]
    public function testWritesEveryKeyOfAMultiKeyRequest(): void
    {
        $label = [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', $this->german() => 'Herbstschlussverkauf'];
        $teaser = [Defaults::LANGUAGE_SYSTEM => 'Save now', $this->german() => 'Jetzt sparen'];

        $result = (new TranslateElement($this->registry(), 'block-a', ['label' => $label, 'teaser' => $teaser]))
            ->apply(new StoredTree([$this->target()]));

        static::assertEquals(
            ['label' => $label, 'teaser' => $teaser, 'headline' => 'Old', 'mediaId' => 'm-1'],
            $this->propertiesOf($result, 'block-a'),
        );
    }

    #[TestDox('reports the target as the only affected element, not its root sibling')]
    public function testAffectedIsOnlyTheTarget(): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);
        $translate->apply(new StoredTree([$this->target(), StoredElementBuilder::create(self::TYPE, 'block-b')->build()]));

        static::assertSame(['block-a'], $translate->affected());
    }

    #[TestDox('mints no element, so the created channel stays empty')]
    public function testCreatesNothing(): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);
        $translate->apply(new StoredTree([$this->target()]));

        static::assertSame([], $translate->created());
    }

    #[TestDox('declares the translate privilege as the one its persisted write requires')]
    public function testWritePrivilegeIsTheTranslatePrivilege(): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);

        static::assertSame('content_layout:translate', $translate->writePrivilege());
    }

    #[TestDox('detaches nothing from a target carrying a slot child and wiring: both stay on it and the orphan and drop channels stay empty')]
    public function testDetachmentChannelsStayEmpty(): void
    {
        $target = StoredElementBuilder::create(self::TYPE, 'block-a')
            ->withProperty('label', [Defaults::LANGUAGE_SYSTEM => 'Autumn sale'])
            ->withDataRequirement('media', 'entity', new StubLoaderConfig())
            ->withConsumer('product', ContextType::Single)
            ->withSlot('content', [StoredElementBuilder::create(self::TYPE, 'block-b')->build()])
            ->build();
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);

        $result = $translate->apply(new StoredTree([$target]));

        $translated = $result->find('block-a');
        static::assertInstanceOf(StoredElement::class, $translated);
        static::assertSame($target->slots, $translated->slots);
        static::assertSame($target->dataRequirements, $translated->dataRequirements);
        static::assertSame($target->contextDefinitions, $translated->contextDefinitions);
        static::assertSame([], $translate->orphaned());
        static::assertSame([], $translate->droppedWiring());
        static::assertSame([], $translate->droppedProperties());
    }

    #[TestDox('rejects an element id that is not in the tree with a 400')]
    public function testUnknownElementRejected(): void
    {
        $translate = new TranslateElement($this->registry(), 'ghost', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $translate->apply(new StoredTree([$this->target()]));
    }

    #[TestDox('rejects an element whose component is not a registered type with a 400')]
    public function testUnregisteredComponentRejected(): void
    {
        $element = StoredElementBuilder::create('Sw:Test:Ghost', 'block-a')->build();
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => [Defaults::LANGUAGE_SYSTEM => 'New']]);

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Test:Ghost'));
        $translate->apply(new StoredTree([$element]));
    }

    #[DataProvider('notTranslatableKeyProvider')]
    #[TestDox('rejects $_dataName with a 400')]
    public function testKeyThatIsNotADeclaredTranslatablePropertyRejected(string $key): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', [$key => [Defaults::LANGUAGE_SYSTEM => 'New']]);

        $this->expectExceptionObject(ContentSystemException::mutationPropertyNotTranslatable('block-a', $key));
        $translate->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTranslatableKeyProvider(): iterable
    {
        yield 'a declared primitive property that is not translatable' => ['headline'];

        yield 'a key the type does not declare at all' => ['ghost'];

        yield 'the empty-string key, which no type declares' => [''];

        // A reference property is wiring filled by the pipeline, never a language map.
        yield 'a declared reference property' => ['media'];

        // PHP turns the member name "42" into the integer array key 42, the shape a decoded request body delivers.
        yield 'a member name PHP casts to an integer array key, reported instead of a TypeError' => ['42'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('rejectedValueProvider')]
    #[TestDox('rejects $_dataName, naming the element, the key and the actual type')]
    public function testValueRejectedByTheDeclaredType(array $values, ContentSystemException $expected): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', $values);

        $this->expectExceptionObject($expected);
        $translate->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function rejectedValueProvider(): iterable
    {
        yield 'a bare string under a translatable key, which only a language map admits' => [
            ['label' => 'Autumn sale'],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'label', 'string'),
        ];

        // StoredValue::fromDecoded([]) yields the list variant, since array_is_list([]) is true, and
        // PropertyType::admits() refuses a list on the translatable branch.
        yield 'an empty map under a translatable key' => [
            ['label' => []],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'label', 'array'),
        ];

        // The entry is judged against the declared primitive: each map below is admitted under a key of the
        // primitive its entry carries.
        yield 'a language map carrying an integer entry under a translatable string' => [
            ['label' => [Defaults::LANGUAGE_SYSTEM => 5]],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'label', 'array'),
        ];

        yield 'a language map carrying a string entry under a translatable integer' => [
            ['columns' => [Defaults::LANGUAGE_SYSTEM => '3']],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'columns', 'array'),
        ];

        yield 'a language map carrying an integer entry under a translatable boolean' => [
            ['visible' => [Defaults::LANGUAGE_SYSTEM => 1]],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'visible', 'array'),
        ];

        yield 'a value under a declared property name PHP casts to an integer array key' => [
            [7 => 'Seven'],
            ContentSystemException::mutationPropertyValueRejected('block-a', '7', 'string'),
        ];
    }

    /**
     * @param array<array-key, string> $map
     */
    #[DataProvider('nonLanguageKeyProvider')]
    #[TestDox('rejects $_dataName with a 400')]
    public function testNonLanguageMapKeyRejected(array $map, string $rejectedKey): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', ['label' => $map]);

        $this->expectExceptionObject(ContentSystemException::mutationPropertyLanguageKeyInvalid('block-a', 'label', $rejectedKey));
        $translate->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<array-key, string>, string}>
     */
    public static function nonLanguageKeyProvider(): iterable
    {
        yield 'the first of two locale codes used as language-map keys' => [
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 'de' => 'Herbstschlussverkauf', 'fr' => 'Soldes d\'automne'],
            'de',
        ];

        yield 'a language-map key PHP casts to an integer array key' => [
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 42 => 'Herbstschlussverkauf'],
            '42',
        ];

        yield 'an uppercase-hex language id, since only lowercase hex is a language id' => [
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 'ABCDEF0123456789ABCDEF0123456789' => 'Herbstschlussverkauf'],
            'ABCDEF0123456789ABCDEF0123456789',
        ];

        yield 'a dashed UUID language id' => [
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 'abcdef01-2345-6789-abcd-ef0123456789' => 'Herbstschlussverkauf'],
            'abcdef01-2345-6789-abcd-ef0123456789',
        ];

        yield 'a 31-character hex language id' => [
            [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 'abcdef0123456789abcdef012345678' => 'Herbstschlussverkauf'],
            'abcdef0123456789abcdef012345678',
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('ruleOrderProvider')]
    #[TestDox('reports $_dataName')]
    public function testFirstFailingRuleReports(array $values, ContentSystemException $expected): void
    {
        $translate = new TranslateElement($this->registry(), 'block-a', $values);

        $this->expectExceptionObject($expected);
        $translate->apply(new StoredTree([$this->target()]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function ruleOrderProvider(): iterable
    {
        yield 'the first of two keys the gate refuses' => [
            ['headline' => [Defaults::LANGUAGE_SYSTEM => 'New'], 'ghost' => [Defaults::LANGUAGE_SYSTEM => 'New']],
            ContentSystemException::mutationPropertyNotTranslatable('block-a', 'headline'),
        ];

        // The value rejection sits on the EARLIER key, so only a gate run over every key before any value is
        // judged reports the later key's rejection.
        yield 'the not-translatable key ahead of a value rejection on an earlier key' => [
            ['label' => 'Autumn sale', 'headline' => [Defaults::LANGUAGE_SYSTEM => 'New']],
            ContentSystemException::mutationPropertyNotTranslatable('block-a', 'headline'),
        ];

        // The language-key failure sits on the EARLIER key, so a per-key loop carrying both rules would report it.
        yield 'the value rejection on a later key ahead of the language-key rejection on an earlier one' => [
            ['label' => [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', 'de' => 'Herbstschlussverkauf'], 'teaser' => 'Save now'],
            ContentSystemException::mutationPropertyValueRejected('block-a', 'teaser', 'string'),
        ];
    }

    private function german(): string
    {
        return Uuid::fromStringToHex('language-german');
    }

    private function target(): StoredElement
    {
        return StoredElementBuilder::create(self::TYPE, 'block-a')
            ->withProperty('label', [Defaults::LANGUAGE_SYSTEM => 'Autumn sale'])
            ->withProperty('headline', 'Old')
            // undeclared by design: a resolvedBy storage key the operation carries without reading it
            ->withProperty('mediaId', 'm-1')
            ->build();
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        return TestElementTypeRegistry::of([
            self::TYPE => ContentSystemElementTypeSpecificationBuilder::create(self::TYPE)
                ->primitive('label', 'string', translatable: true)
                ->primitive('teaser', 'string', translatable: true)
                ->primitive('7', 'string', translatable: true)
                ->primitive('columns', 'integer', translatable: true)
                ->primitive('visible', 'boolean', translatable: true)
                ->primitive('headline', 'string')
                // absent from the target, so a default overlay would surface in the full written property map
                ->primitive('tag', 'string', default: 'h1')
                ->reference('media', StubStruct::class)
                ->build(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function propertiesOf(StoredTree $tree, string $id): array
    {
        $element = $tree->find($id);
        static::assertInstanceOf(StoredElement::class, $element);

        return array_map(
            static fn (StoredValue $value): mixed => $value->jsonSerialize(),
            $element->properties()
        );
    }
}
