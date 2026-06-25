<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;

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
     * @param array<string, mixed> $rawRange
     * @param array{min?: int|float, max?: int|float}|null $expected
     */
    #[DataProvider('narrowsRangeProvider')]
    #[TestDox('narrows raw range $_dataName onto the value type')]
    public function testNarrowsRange(array $rawRange, ?array $expected): void
    {
        $dto = new StyleOptionSpecificationDto('integer', null, $rawRange, null, null, null);

        static::assertSame($expected, $dto->toStyleOptionSpecification('col-span', 'core')->valueType()->range());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array{min?: int|float, max?: int|float}|null}>
     */
    public static function narrowsRangeProvider(): iterable
    {
        yield 'fully numeric range passes through unchanged' => [['min' => 1, 'max' => 12], ['min' => 1, 'max' => 12]];
        yield 'non-numeric min dropped, numeric max kept' => [['min' => 'a', 'max' => 12], ['max' => 12]];
        yield 'entirely non-numeric range reduces to null' => [['min' => 'a'], null];
    }
}
