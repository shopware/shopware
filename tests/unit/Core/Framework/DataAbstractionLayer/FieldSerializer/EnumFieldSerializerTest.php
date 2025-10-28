<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestIntegerEnum;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestStringEnum;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(EnumFieldSerializer::class)]
#[Package('framework')]
#[Group('FieldSerializer')]
#[Group('DAL')]
class EnumFieldSerializerTest extends TestCase
{
    private EnumFieldSerializer $enumFieldSerializer;

    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(
                $this->createMock(TranslatorInterface::class)
            ),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->enumFieldSerializer = new EnumFieldSerializer(
            $validator,
            $this->definitionInstanceRegistry
        );
    }

    /**
     * @return \Generator<string, array{
     *     0: Field,
     *     1: string|int|bool|null,
     *     2: string|int|null,
     *     3: bool,
     *     4: EntityExistence
     * }>
     */
    public static function serializerProvider(): \Generator
    {
        $update = new EntityExistence('product', [], true, false, false, []);
        $create = new EntityExistence('product', [], false, false, false, []);

        $requiredString = (new EnumField('name', 'name', TestStringEnum::Regular))->addFlags(new Required());
        $optionalString = new EnumField('name', 'name', TestStringEnum::Regular);

        $requiredInt = (new EnumField('name', 'name', TestIntegerEnum::One))->addFlags(new Required());
        $optionalInt = new EnumField('name', 'name', TestIntegerEnum::One);

        yield 'Create string with null and required' => [$requiredString, null, null, true, $create];
        yield 'Create string with null and optional' => [$optionalString, null, null, false, $create];
        yield 'Update string with null and required' => [$requiredString, null, null, true, $update];
        yield 'Update string with null and optional' => [$optionalString, null, null, false, $update];
        yield 'Create string with empty and required' => [$requiredString, '', null, true, $create];
        yield 'Create string with empty and optional' => [$optionalString, '', null, false, $create];
        yield 'Update string with empty and required' => [$requiredString, '', null, true, $update];
        yield 'Update string with empty and optional' => [$optionalString, '', null, false, $update];
        yield 'Create string with space and required' => [$requiredString, ' ', null, true, $create];
        yield 'Create string with space and optional' => [$optionalString, ' ', null, false, $create];
        yield 'Update string with space and required' => [$requiredString, ' ', null, true, $update];
        yield 'Update string with space and optional' => [$optionalString, ' ', null, false, $update];

        yield 'Create int with null and required' => [$requiredInt, null, null, true, $create];
        yield 'Create int with null and optional' => [$optionalInt, null, null, false, $create];
        yield 'Update int with null and required' => [$requiredInt, null, null, true, $update];
        yield 'Update int with null and optional' => [$optionalInt, null, null, false, $update];
        yield 'Create int with string and optional' => [$optionalInt, '', null, true, $create];
        yield 'Create int with false and required' => [$optionalInt, false, null, true, $create];
        yield 'Create int from 0 and required' => [$requiredInt, 0, TestIntegerEnum::Zero->value, false, $create];
        yield 'Create int from 1 null and optional' => [$optionalInt, 1, TestIntegerEnum::One->value, false, $create];

        yield 'Create null with misspelled string' => [$optionalString, 'leading-space', null, false, $create];
        yield 'Create string with leading space' => [$optionalString, ' leading-space', TestStringEnum::LeadingSpace->value, false, $create];
        yield 'Create string with trailing space' => [$optionalString, 'trailing-space ', TestStringEnum::TrailingSpace->value, false, $create];
        yield 'Fail creation with required string and misspelled value' => [$requiredString, 'leading-space', null, true, $create];
    }

    public static function decoderProvider(): \Generator
    {
        $intEnumFieldOne = new EnumField('name', 'name', TestIntegerEnum::One);
        yield 'Valid string enum' => [new EnumField('name', 'name', TestStringEnum::Regular), 'string', TestStringEnum::Regular];
        yield 'Null from string enum' => [new EnumField('name', 'name', TestStringEnum::Regular), null, null];
        yield 'Invalid string enum is null' => [new EnumField('name', 'name', TestStringEnum::Regular), 'invalid value', null];
        yield 'Valid int enum' => [new EnumField('name', 'name', TestIntegerEnum::One), 1, TestIntegerEnum::One];
        yield 'Int enum from db string' => [new EnumField('name', 'name', TestIntegerEnum::One), '1', TestIntegerEnum::One];
        yield 'Exception from non-numeric string ' => [$intEnumFieldOne, '1 error', TestIntegerEnum::One, DataAbstractionLayerException::expectedFieldValueOfTypeWithValue($intEnumFieldOne, Types::INTEGER, '1 error')];
        yield 'Null from int enum' => [new EnumField('name', 'name', TestIntegerEnum::One), null, null];
        yield 'Invalid int enum is null' => [new EnumField('name', 'name', TestIntegerEnum::One), '-99', null];
    }

    #[DataProvider('serializerProvider')]
    public function testSerialize(EnumField $field, string|int|bool|null $value, string|int|null $expected, bool $expectError, EntityExistence $existence): void
    {
        $field->compile($this->definitionInstanceRegistry);

        $actual = null;
        $exception = null;

        try {
            $kv = new KeyValuePair($field->getPropertyName(), $value, true);

            $params = $this->createWriteParameterBag();

            $actual = $this->enumFieldSerializer->encode($field, $existence, $kv, $params)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        // error cases
        if ($expectError) {
            static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'This value should not be blank.');
            static::assertSame('/' . $field->getPropertyName(), $exception->getViolations()->get(0)->getPropertyPath());

            return;
        }

        static::assertNull($exception);
        static::assertSame($expected, $actual);
    }

    #[DataProvider('decoderProvider')]
    public function testDecode(EnumField $field, string|int|null $value, ?\BackedEnum $expected, ?\Throwable $expectedException = null): void
    {
        $exception = null;
        $actual = null;

        try {
            $actual = $this->enumFieldSerializer->decode($field, $value);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($expectedException !== null) {
            static::assertInstanceOf($expectedException::class, $exception);
            static::assertSame($expectedException->getMessage(), $exception->getMessage());

            return;
        }

        static::assertSame($expected, $actual);
    }

    public function testInvalidField(): void
    {
        $field = new IntField('int', 'int');
        $this->expectExceptionObject(
            DataAbstractionLayerException::invalidSerializerField(EnumField::class, $field)
        );
        $this->enumFieldSerializer->decode($field, null);
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
}
