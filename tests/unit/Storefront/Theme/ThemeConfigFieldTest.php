<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\ThemeConfigField;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigField::class)]
class ThemeConfigFieldTest extends TestCase
{
    #[DataProvider('supportedValues')]
    public function testSetValueAcceptsFutureValueTypes(mixed $value): void
    {
        $field = new ThemeConfigField();
        $field->setValue($value);

        static::assertSame($value, $field->getValue());
    }

    public static function supportedValues(): \Generator
    {
        yield 'array' => [['value']];
        yield 'boolean' => [true];
        yield 'float' => [1.5];
        yield 'integer' => [1];
        yield 'string' => ['value'];
        yield 'null for an emptied value' => [null];
    }

    public function testSetValueRejectsUnsupportedValueWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing a value that is neither an array, boolean, float, integer, string, nor null is deprecated and will not be allowed in v6.8.0.0.'
        ));

        // @phpstan-ignore argument.type (Intentional legacy value that must fail when the feature is active.)
        (new ThemeConfigField())->setValue(new \stdClass());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSetValueAcceptsUnsupportedValueBeforeFeatureIsActive(): void
    {
        $value = new \stdClass();
        $field = new ThemeConfigField();
        // @phpstan-ignore argument.type (Intentional legacy value that remains accepted before the feature is active.)
        $field->setValue($value);

        static::assertSame($value, $field->getValue());
    }
}
