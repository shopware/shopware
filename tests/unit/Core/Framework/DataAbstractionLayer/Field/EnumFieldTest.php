<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestIntegerEnum;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestStringEnum;

/**
 * @internal
 */
#[Package('core')]
#[Group('Field')]
#[Group('DAL')]
class EnumFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('enumFieldDataProvider')]
    public function testEnumFieldSerializer(
        string $type,
        mixed $input,
        string|int|null $expected,
        \BackedEnum $enum,
        array $flags = []
    ): void {
        $serializer = static::getContainer()->get(EnumFieldSerializer::class);

        $name = 'enum_' . Uuid::randomHex();
        $data = new KeyValuePair($name, $input, false);

        if ($type === 'writeException') {
            $this->expectException(WriteConstraintViolationException::class);

            try {
                $serializer->encode(
                    $this->getEnumField($name, $enum, $flags),
                    EntityExistence::createEmpty(),
                    $data,
                    $this->getWriteParameterBagMock()
                )->current();
            } catch (WriteConstraintViolationException $e) {
                static::assertSame('/' . $name, $e->getViolations()->get(0)->getPropertyPath());
                static::assertSame($expected, $e->getViolations()->get(0)->getMessage());

                throw $e;
            }
        }

        if ($type === 'assertion') {
            static::assertSame(
                $expected,
                $serializer->encode(
                    $this->getEnumField($name, $enum, $flags),
                    EntityExistence::createEmpty(),
                    $data,
                    $this->getWriteParameterBagMock()
                )->current()
            );
        }
    }

    /**
     * @return array<string, array{string, bool|string|int|\stdClass|null, string|int|null, \BackedEnum, array<Flag>}>
     */
    public static function enumFieldDataProvider(): array
    {
        $validationFailedForInt = 'This value should satisfy at least one of the following constraints: [1] This value should be of type integer. [2] This value should be null.';
        $validationFailedForString = 'This value should satisfy at least one of the following constraints: [1] This value should be of type string. [2] This value should be null.';

        return [
            'regular string with other case as reference to enum' => ['assertion', 'string', TestStringEnum::Regular->value, TestStringEnum::TrailingSpace, []],
            'regular int with other case as reference to enum' => ['assertion', 0, TestIntegerEnum::Zero->value, TestIntegerEnum::One, []],
            'ignored misspelled values' => ['assertion', 'leading-space', null, TestStringEnum::Regular, []],
            'respect leading spaces' => ['assertion', ' leading-space', TestStringEnum::LeadingSpace->value, TestStringEnum::Regular, []],
            'respect trailing spaces' => ['assertion', 'string', TestStringEnum::Regular->value, TestStringEnum::TrailingSpace, []],
            'error for misspelled values and required values' => ['writeException', 'leading-space', 'This value should not be blank.', TestStringEnum::LeadingSpace, [new Required()]],
            'error for unsupported scalars' => ['writeException', false, $validationFailedForInt, TestIntegerEnum::One, []],
            'error for invalid objects' => ['writeException', new \stdClass(), $validationFailedForInt, TestIntegerEnum::One, []],
            'error for mismatching scalars' => ['writeException', 0, $validationFailedForString, TestStringEnum::Regular, []],
        ];
    }

    private function getWriteParameterBagMock(): WriteParameterBag
    {
        $mockBuilder = $this->getMockBuilder(WriteParameterBag::class);
        $mockBuilder->disableOriginalConstructor();

        return $mockBuilder->getMock();
    }

    /**
     * @param list<Flag> $flags
     */
    private function getEnumField(string $name, \BackedEnum $enum, array $flags = []): EnumField
    {
        $field = new EnumField($name, $name, $enum);

        if ($flags) {
            $field->addFlags(new ApiAware(), ...$flags);
        }

        return $field;
    }
}
