<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StrictChoiceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\Choice as ChoiceConstraint;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IntFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class IntFieldSerializerTest extends TestCase
{
    private IntFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private RecursiveValidator $validator;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new IntFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2]));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 3, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 3], $encoded);
    }

    public function testChoiceStrictAcceptsValidValue(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 3, true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 3),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    public function testChoiceStrictViolationIsReportedTogetherWithRangeViolation(): void
    {
        $field = (new IntField('test', 'test', 0, 5))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        try {
            iterator_to_array($this->serializer->encode($field, EntityExistence::createEmpty(), new KeyValuePair('test', 10, true), $this->createWriteParameterBag()));
            static::fail('A strict choice int field must reject values that are out of range and not a valid choice.');
        } catch (WriteConstraintViolationException $exception) {
            $codes = array_map(static fn (ConstraintViolationInterface $violation): ?string => $violation->getCode(), iterator_to_array($exception->getViolations()));
            static::assertSame([Range::NOT_IN_RANGE_ERROR, ChoiceConstraint::NO_SUCH_CHOICE_ERROR], $codes);
        }
    }

    public function testChoiceStrictAcceptsValueOfEnumProvider(): void
    {
        $serializer = $this->createSerializerWithEnumProvider([3]);
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $encoded = iterator_to_array($serializer->encode($field, EntityExistence::createEmpty(), new KeyValuePair('test', 3, true), $this->createWriteParameterBag()));

        static::assertSame(['test' => 3], $encoded);
    }

    public function testChoiceStrictRejectsValueUnknownToEnumProvider(): void
    {
        $serializer = $this->createSerializerWithEnumProvider([3]);
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 4),
            ])
        ));

        iterator_to_array($serializer->encode($field, EntityExistence::createEmpty(), new KeyValuePair('test', 4, true), $this->createWriteParameterBag()));
    }

    public function testRequiredNullValueThrowsConstraintViolation(): void
    {
        $field = $this->createField();

        try {
            $this->encodeValue($field, null);
            static::fail('Required int fields must reject null values.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/count', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testWrongValueTypeThrowsConstraintViolation(): void
    {
        $field = $this->createField();

        try {
            $this->encodeValue($field, 'foo');
            static::fail('Int fields must reject non-integer values.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame('/count', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testZeroValueIsEncoded(): void
    {
        $field = $this->createField();

        static::assertSame(['count' => 0], $this->encodeValue($field, 0));
    }

    public function testIntegerValueIsEncoded(): void
    {
        $field = $this->createField();

        static::assertSame(['count' => 15], $this->encodeValue($field, 15));
    }

    public function testOptionalNullValueIsEncoded(): void
    {
        $field = $this->createField(required: false);

        static::assertSame(['count' => null], $this->encodeValue($field, null));
    }

    private function createField(bool $required = true): IntField
    {
        $field = new IntField('count', 'count');
        if ($required) {
            $field->addFlags(new Required());
        }
        $field->compile($this->definitionInstanceRegistry);

        return $field;
    }

    /**
     * @return array<string, int|null>
     */
    private function encodeValue(IntField $field, mixed $value): array
    {
        return iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('count', $value, false),
            $this->createWriteParameterBag()
        ));
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    /**
     * @param list<int> $choices
     */
    private function createSerializerWithEnumProvider(array $choices): IntFieldSerializer
    {
        return new IntFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
            new StrictChoiceResolver([StrictChoiceResolverTest::createEnumProvider(ProductDefinition::ENTITY_NAME, 'test', $choices)])
        );
    }
}
