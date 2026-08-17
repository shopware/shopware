<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementListFieldSerializer::class)]
class StoredElementListFieldSerializerTest extends TestCase
{
    #[TestDox('seeds the type primitive defaults into a raw layout payload before encode')]
    public function testNormalizeSeedsPrimitiveDefaultsIntoRawPayload(): void
    {
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [['id' => 'el', 'component' => 'Sw:Block', 'properties' => []]]];

        $result = $this->serializerWithRealSeeder()->normalize($field, $data, $this->parameters());

        static::assertSame([['id' => 'el', 'component' => 'Sw:Block', 'properties' => ['headline' => 'Hi']]], $result['elements']);
    }

    #[TestDox('wraps a single StoredElement value into a list and seeds the type primitive defaults onto it')]
    public function testNormalizeWrapsSingleStoredElementIntoListAndSeedsPrimitiveDefaults(): void
    {
        $field = $this->createField();
        $element = StoredElementBuilder::create('Sw:Block', 'el')->build();

        $result = $this->serializerWithRealSeeder()->normalize($field, ['id' => 'layout-1', 'elements' => $element], $this->parameters());

        static::assertSame([['id' => 'el', 'component' => 'Sw:Block', 'properties' => ['headline' => 'Hi']]], $result['elements']);
    }

    #[TestDox('leaves a non-list layout value untouched')]
    public function testNormalizeLeavesNonListValueUntouched(): void
    {
        $field = $this->createField();

        $result = $this->serializerWithRealSeeder()->normalize($field, ['elements' => 'not-a-list'], $this->parameters());

        static::assertSame(['elements' => 'not-a-list'], $result);
    }

    /**
     * The pinned-order test. It proves the write chain runs decode → validate → seed → style-normalize →
     * reconcile, in that sequence rather than merely running each step: the three boundary passes record into
     * one shared log through the collaborators each of them reaches, and the two rejection tests below pin the
     * two links ahead of them by showing that neither an undecodable nor an ill-formed payload gets that far.
     */
    #[TestDox('runs seeding, style normalization and attribution reconciliation in the pinned order')]
    public function testNormalizeRunsTheWriteChainInThePinnedOrder(): void
    {
        $calls = [];
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [['id' => 'el', 'component' => 'Sw:Block', 'properties' => []]]];

        $this->recordingSerializer($calls)->normalize($field, $data, $this->parameters());

        static::assertSame(['seed', 'style', 'reconcile'], $calls);
    }

    #[TestDox('rejects an undecodable payload before any write-boundary pass runs')]
    public function testNormalizeRejectsAnUndecodablePayloadBeforeTheBoundary(): void
    {
        $calls = [];
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [['id' => 'el', 'component' => 'Sw:Block', 'properties' => [12 => 'x']]]];

        try {
            $this->recordingSerializer($calls)->normalize($field, $data, $this->parameters());
            static::fail('Expected the decode step to reject the payload.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAP_KEY, $exception->getViolations()->get(0)->getCode());
            static::assertSame([], $calls);
        }
    }

    #[TestDox('rejects a forest repeating an element id before any write-boundary pass runs')]
    public function testNormalizeRejectsADuplicateElementIdBeforeTheBoundary(): void
    {
        $calls = [];
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [
            ['id' => 'el', 'component' => 'Sw:Block', 'properties' => []],
            ['id' => 'el', 'component' => 'Sw:Block', 'properties' => []],
        ]];

        try {
            $this->recordingSerializer($calls)->normalize($field, $data, $this->parameters());
            static::fail('Expected the tree-global validation step to reject the payload.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getViolations()->get(0)->getCode());
            static::assertSame([], $calls);
        }
    }

    #[TestDox('expands a partially specified breakpoint map on a raw-array write that never passed the Administration')]
    public function testNormalizeNormalizesStyleOnARawArrayPayload(): void
    {
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [
            ['id' => 'el', 'component' => 'Sw:Block', 'properties' => [], 'style' => ['display' => ['xs' => false]]],
        ]];

        $result = $this->serializer()->normalize($field, $data, $this->parameters());

        static::assertSame(
            [['id' => 'el', 'component' => 'Sw:Block', 'properties' => [], 'style' => [
                'display' => ['xs' => false, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true],
            ]]],
            $result['elements']
        );
    }

    #[TestDox('memoizes the boundary-processed tree on the write context under the written row id')]
    public function testNormalizeMemoizesTheBoundaryProcessedTree(): void
    {
        $context = Context::createDefaultContext();
        $field = $this->createField();
        $data = ['id' => 'layout-1', 'elements' => [['id' => 'el', 'component' => 'Sw:Block', 'properties' => []]]];

        $this->serializerWithRealSeeder()->normalize($field, $data, $this->parametersFor($context));

        $memo = $context->getExtension(LayoutWriteContext::EXTENSION_NAME);
        static::assertInstanceOf(LayoutWriteContext::class, $memo);

        $tree = $memo->consume('content_layout', 'layout-1');
        static::assertNotNull($tree);
        static::assertSame('Hi', $tree->roots[0]->property('headline')?->asString());
    }

    #[TestDox('encodes a single StoredElement wrapped to a list as a JSON string')]
    public function testEncodeWithSingleStoredElementWrapsAndEncodesAsJson(): void
    {
        $field = $this->createField();
        $element = StoredElementBuilder::create('text', 'elem-1')->build();

        $kvPair = new KeyValuePair('elements', $element, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );

        static::assertSame(
            [['id' => 'elem-1', 'component' => 'text', 'properties' => []]],
            $this->decodeJson($result['elements'])
        );
    }

    #[TestDox('encodes a StoredElement list as a JSON string')]
    public function testEncodeWithStoredElementListYieldsJson(): void
    {
        $field = $this->createField();
        $elements = [
            StoredElementBuilder::create('text', 'elem-1')->build(),
            StoredElementBuilder::create('image', 'elem-2')->build(),
        ];

        $kvPair = new KeyValuePair('elements', $elements, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );

        static::assertSame(
            [
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
                ['id' => 'elem-2', 'component' => 'image', 'properties' => []],
            ],
            $this->decodeJson($result['elements'])
        );
    }

    #[TestDox('decodes a raw array payload through the codec so storage holds the canonical shape')]
    public function testEncodeWithRawArrayYieldsTheCanonicalShape(): void
    {
        $field = $this->createField();

        // "slots" carrying an empty list and "style" carrying nothing are both dropped by the canonical shape,
        // which is what distinguishes a codec round trip from passing the raw payload through verbatim.
        $kvPair = new KeyValuePair('elements', [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => ['headline' => 'Hi'], 'style' => []],
        ], false);

        $result = iterator_to_array(
            $this->serializer()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );

        static::assertSame(
            [['id' => 'elem-1', 'component' => 'text', 'properties' => ['headline' => 'Hi']]],
            $this->decodeJson($result['elements'])
        );
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createField();
        $kvPair = new KeyValuePair('elements', null, false);

        $result = iterator_to_array(
            $this->serializer()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );

        static::assertArrayHasKey('elements', $result);
        static::assertNull($result['elements']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        // TranslatedField does not implement StorageAware
        $invalidField = new TranslatedField('elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('elements', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array(
            $this->serializer()->encode($invalidField, $this->existence(), $kvPair, $this->parameters())
        );
    }

    #[TestDox('throws exception when encode receives non-array non-null non-element value')]
    public function testEncodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createField();
        $kvPair = new KeyValuePair('elements', 'invalid-string', false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements', 'array', 'string')
        );

        iterator_to_array(
            $this->serializerWithPassthroughValidator()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    #[DataProvider('numericWiringKeyProvider')]
    #[TestDox('rejects $_dataName at the write boundary as a constraint violation rather than an unhandled error')]
    public function testEncodeRemapsANumericWiringKeyToAConstraintViolation(array $payload): void
    {
        $field = $this->createField();
        $kvPair = new KeyValuePair('elements', [$payload], false);

        try {
            iterator_to_array(
                $this->serializerWithPassthroughValidator()->encode($field, $this->existence(), $kvPair, $this->parameters())
            );
        } catch (WriteConstraintViolationException $exception) {
            static::assertCount(1, $exception->getViolations());
            static::assertSame('/elements', $exception->getViolations()->get(0)->getPropertyPath());
            static::assertSame(
                ContentSystemException::INVALID_MAP_KEY,
                $exception->getViolations()->get(0)->getCode()
            );

            return;
        }

        static::fail('Encoding a numeric wiring key did not raise a WriteConstraintViolationException.');
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function numericWiringKeyProvider(): iterable
    {
        yield 'a numeric property key' => [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => [12 => 'x']],
        ];

        yield 'a numeric data requirement key' => [
            [
                'id' => 'elem-1',
                'component' => 'text',
                'dataRequirements' => [7 => ['source' => 'entity', 'config' => []]],
            ],
        ];

        yield 'a numeric slot key' => [
            [
                'id' => 'elem-1',
                'component' => 'text',
                'slots' => [3 => [['id' => 'child-1', 'component' => 'text']]],
            ],
        ];
    }

    #[TestDox('decodes a JSON string to a stored element list')]
    public function testDecodeWithJsonStringReturnsStoredElementList(): void
    {
        $field = $this->createField();

        $json = json_encode([
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
            ['id' => 'elem-2', 'component' => 'image', 'properties' => []],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer()->decode($field, $json);

        static::assertIsArray($result);
        static::assertContainsOnlyInstancesOf(StoredElement::class, $result);
        static::assertSame(['elem-1', 'elem-2'], array_map(static fn (StoredElement $e): string => $e->id, $result));
    }

    #[TestDox('decodes an array directly to a stored element list')]
    public function testDecodeWithArrayReturnsStoredElementList(): void
    {
        $field = $this->createField();

        $result = $this->serializer()->decode($field, [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
        ]);

        static::assertIsArray($result);
        static::assertSame(['elem-1'], array_map(static fn (StoredElement $e): string => $e->id, $result));
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createField();

        static::assertNull($this->serializer()->decode($field, null));
    }

    #[TestDox('decodes empty array to empty array')]
    public function testDecodeWithEmptyArrayReturnsEmptyArray(): void
    {
        $field = $this->createField();

        static::assertSame([], $this->serializer()->decode($field, []));
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonStoredElementListField(): void
    {
        $invalidField = new JsonField('elements', 'elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StoredElementListField::class, JsonField::class)
        );

        $this->serializer()->decode($invalidField, []);
    }

    #[TestDox('throws invalidFieldValueType on an invalid decode value: $_dataName')]
    #[DataProvider('throwsOnInvalidDecodeValueProvider')]
    public function testDecodeThrowsOnInvalidValue(mixed $value, string $path, string $expected, string $given): void
    {
        $field = $this->createField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType($path, $expected, $given)
        );

        $this->serializer()->decode($field, $value);
    }

    /**
     * @return iterable<string, array{mixed, string, string, string}>
     */
    public static function throwsOnInvalidDecodeValueProvider(): iterable
    {
        yield 'non-array scalar value' => [42, 'elements', 'array', 'integer'];

        yield 'associative array instead of indexed list' => [
            ['key' => ['id' => 'elem-1', 'component' => 'text', 'properties' => []]],
            'layout',
            'list of elements',
            'associative array',
        ];

        yield 'array with non-array element' => [['not-an-array'], 'layout[0]', 'array', 'string'];
    }

    #[TestDox('returns Type and All constraints without Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $constraints = $this->serializer()->buildConstraints($this->createField());

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(All::class, $constraints[1]);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $constraints = $this->serializer()->buildConstraints($this->createRequiredField());

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    #[TestDox('throws exception when buildConstraints receives wrong field type')]
    public function testBuildConstraintsThrowsOnNonStoredElementListField(): void
    {
        $invalidField = new JsonField('elements', 'elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StoredElementListField::class, JsonField::class)
        );

        $this->serializer()->buildConstraints($invalidField);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeJson(mixed $encoded): array
    {
        static::assertIsString($encoded);

        $decoded = json_decode($encoded, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    private function serializer(): StoredElementListFieldSerializer
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return new StoredElementListFieldSerializer(
            $validator,
            static::createStub(DefinitionInstanceRegistry::class),
            $this->elementSerializer(),
            $this->codec(),
            new ViolationConstraintMapper(),
            $this->boundary($this->passthroughSeeder()),
        );
    }

    /**
     * A passthrough validator raises no violations — used when encoding element objects and payloads whose
     * rejection is the codec's to make, so the constraint pass cannot pre-empt what the test is asserting.
     */
    private function serializerWithPassthroughValidator(): StoredElementListFieldSerializer
    {
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        return new StoredElementListFieldSerializer(
            $passthroughValidator,
            static::createStub(DefinitionInstanceRegistry::class),
            $this->elementSerializer(),
            $this->codec(),
            new ViolationConstraintMapper(),
            $this->boundary($this->passthroughSeeder()),
        );
    }

    private function serializerWithRealSeeder(): StoredElementListFieldSerializer
    {
        $specs = ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')->primitive('headline', 'string', default: 'Hi')->build()];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new StoredElementListFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $this->elementSerializer(),
            $this->codec(),
            new ViolationConstraintMapper(),
            $this->boundary(new LayoutDefaultSeeder($registry, new PrimitiveDefaultProvider())),
        );
    }

    /**
     * A serializer whose three boundary passes append their own name to $calls as they run. Each pass is
     * recorded through a collaborator it cannot skip: the seeder asks the type registry whether a component
     * is known, the style normalizer reads the option registry, and the reconciler is called once per forest.
     *
     * @param list<string> $calls
     */
    private function recordingSerializer(array &$calls): StoredElementListFieldSerializer
    {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturnCallback(function () use (&$calls): bool {
            $calls[] = 'seed';

            return false;
        });

        $styleRegistry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $styleRegistry->method('all')->willReturnCallback(function () use (&$calls): array {
            $calls[] = 'style';

            return [];
        });

        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willReturnCallback(function (array $forest) use (&$calls): array {
            $calls[] = 'reconcile';

            return $forest;
        });

        $boundary = new LayoutWriteBoundary(
            new LayoutDefaultSeeder($typeRegistry, new PrimitiveDefaultProvider()),
            new ElementStyleNormalizer($styleRegistry, new BoxSpacingNormalizer()),
            $reconciler,
        );

        return new StoredElementListFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $this->elementSerializer(),
            $this->codec(),
            new ViolationConstraintMapper(),
            $boundary,
        );
    }

    /**
     * Attribution reconciliation is covered by its own tests, so a passthrough reconciler keeps these
     * assertions about the serializer's contract. The style registry holds the one breakpoint-aware option
     * with a declared default that the style assertions need.
     */
    private function boundary(LayoutDefaultSeeder $seeder): LayoutWriteBoundary
    {
        return new LayoutWriteBoundary($seeder, $this->styleNormalizer(), $this->passthroughReconciler());
    }

    private function passthroughSeeder(): LayoutDefaultSeeder
    {
        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturnArgument(0);

        return $seeder;
    }

    private function styleNormalizer(): ElementStyleNormalizer
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'display' => new StyleOptionSpecification(
                'display',
                new StyleOptionValueType(StyleOptionValueType::TYPE_BOOLEAN, null, null, null, true),
                true,
                null,
                'test',
            ),
        ]);

        return new ElementStyleNormalizer($registry, new BoxSpacingNormalizer());
    }

    private function passthroughReconciler(): AttributionReconciler
    {
        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willReturnArgument(0);

        return $reconciler;
    }

    /**
     * Loader config semantics stay out of these tests: a provider that accepts any config keeps them about the
     * field serializer's own contract rather than about what a given source's config must contain.
     */
    private function codec(): StoredTreeCodec
    {
        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturn(new StubLoaderConfig());

        return new StoredTreeCodec(new StoredElementCodec($configProvider));
    }

    /**
     * buildConstraints must return at least one valid constraint so that new All([...]) does not throw.
     *
     * @return ContentElementFieldSerializer&Stub
     */
    private function elementSerializer(): ContentElementFieldSerializer
    {
        $elementSerializer = static::createStub(ContentElementFieldSerializer::class);
        $elementSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        return $elementSerializer;
    }

    private function existence(): EntityExistence
    {
        return new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
    }

    private function parameters(): WriteParameterBag
    {
        return $this->parametersFor(Context::createDefaultContext());
    }

    private function parametersFor(Context $context): WriteParameterBag
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('content_layout');

        return new WriteParameterBag(
            $definition,
            WriteContext::createFromContext($context),
            '/0',
            new WriteCommandQueue()
        );
    }

    private function createField(): StoredElementListField
    {
        $field = new StoredElementListField('elements', 'elements');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createRequiredField(): StoredElementListField
    {
        $field = new StoredElementListField('elements', 'elements');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
