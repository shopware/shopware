<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;

/**
 * @internal
 */
#[CoversClass(StyleOptionSpecificationDto::class)]
class StyleOptionSpecificationDtoTest extends TestCase
{
    #[TestDox('maps every declared facet plus the supplied name and source onto the specification')]
    public function testMapsFieldsOntoSpecification(): void
    {
        $dto = new StyleOptionSpecificationDto('string', ['start', 'end'], null, 64, 'start', ['component' => 'select']);

        $spec = $dto->toStyleOptionSpecification('align-self', 'core');

        static::assertSame('align-self', $spec->name());
        static::assertSame('core', $spec->source());
        static::assertSame('string', $spec->valueType()->type());
        static::assertSame(['start', 'end'], $spec->valueType()->enum());
        static::assertSame(64, $spec->valueType()->maxLength());
        static::assertSame('start', $spec->valueType()->default());
        static::assertSame(['component' => 'select'], $spec->toSchema()['adminUI']);
    }

    /**
     * @param array{min?: int|float, max?: int|float}|null $expected
     */
    #[DataProvider('narrowsRangeProvider')]
    #[TestDox('narrows raw range $_dataName onto the value type')]
    public function testNarrowsRange(mixed $rawRange, ?array $expected): void
    {
        $dto = new StyleOptionSpecificationDto('integer', null, $rawRange, null, null, null);

        static::assertSame($expected, $dto->toStyleOptionSpecification('col-span', 'core')->valueType()->range());
    }

    #[TestDox('narrows a non-array enum to null on the value type')]
    public function testNarrowsNonArrayEnumToNull(): void
    {
        $dto = new StyleOptionSpecificationDto('string', 'not-an-array', null, null, null, null);

        static::assertNull($dto->toStyleOptionSpecification('align-self', 'core')->valueType()->enum());
    }

    #[TestDox('narrows a non-scalar default to null on the value type')]
    public function testNarrowsNonScalarDefaultToNull(): void
    {
        $dto = new StyleOptionSpecificationDto('integer', null, null, null, ['not', 'a', 'scalar'], null);

        static::assertNull($dto->toStyleOptionSpecification('col-span', 'core')->valueType()->default());
    }

    #[TestDox('collapses an empty adminUI map to null on the schema')]
    public function testCollapsesEmptyAdminUiToNull(): void
    {
        $dto = new StyleOptionSpecificationDto('boolean', null, null, null, null, []);

        static::assertNull($dto->toStyleOptionSpecification('display', 'core')->toSchema()['adminUI']);
    }

    /**
     * The string default cap stands in for "no declared maxLength", so a non-integer raw maxLength that
     * narrows to null is observable as the default 255 rather than the raw value.
     */
    #[DataProvider('narrowsMaxLengthProvider')]
    #[TestDox('narrows raw maxLength $_dataName onto the value type')]
    public function testNarrowsMaxLength(mixed $rawMaxLength, int $expected): void
    {
        $dto = new StyleOptionSpecificationDto('string', null, null, $rawMaxLength, null, null);

        static::assertSame($expected, $dto->toStyleOptionSpecification('margin', 'core')->valueType()->maxLength());
    }

    /**
     * @return iterable<string, array{mixed, array{min?: int|float, max?: int|float}|null}>
     */
    public static function narrowsRangeProvider(): iterable
    {
        yield 'fully numeric range passes through unchanged' => [['min' => 1, 'max' => 12], ['min' => 1, 'max' => 12]];
        yield 'non-numeric min dropped, numeric max kept' => [['min' => 'a', 'max' => 12], ['max' => 12]];
        yield 'entirely non-numeric range reduces to null' => [['min' => 'a'], null];
        yield 'non-array range reduces to null' => ['not-an-array', null];
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function narrowsMaxLengthProvider(): iterable
    {
        yield 'positive integer passes through' => [64, 64];
        yield 'non-integer narrows to null and the default cap applies' => ['64', StyleOptionValueType::DEFAULT_STRING_MAX_LENGTH];
    }
}
