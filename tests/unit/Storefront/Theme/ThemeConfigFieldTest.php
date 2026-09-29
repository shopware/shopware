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

    public function testAccessorsRoundTrip(): void
    {
        $field = new ThemeConfigField();

        $field->setName('sw-color-brand-primary');
        $field->setLabelSnippetKey('sw-theme.fields.sw-color-brand-primary.label');
        $field->setHelpTextSnippetKey('sw-theme.fields.sw-color-brand-primary.helpText');
        $field->setType('color');
        $field->setEditable(true);
        $field->setBlock('themeColors');
        $field->setSection('mainColors');
        $field->setTab('colors');
        $field->setOrder(1);
        $field->setTabOrder(2);
        $field->setSectionOrder(3);
        $field->setBlockOrder(4);
        $field->setCustom(['componentName' => 'sw-colorpicker']);
        $field->setScss(false);
        $field->setFullWidth(true);

        static::assertSame('sw-color-brand-primary', $field->getName());
        static::assertSame('sw-theme.fields.sw-color-brand-primary.label', $field->getLabelSnippetKey());
        static::assertSame('sw-theme.fields.sw-color-brand-primary.helpText', $field->getHelpTextSnippetKey());
        static::assertSame('color', $field->getType());
        static::assertTrue($field->getEditable());
        static::assertSame('themeColors', $field->getBlock());
        static::assertSame('mainColors', $field->getSection());
        static::assertSame('colors', $field->getTab());
        static::assertSame(1, $field->getOrder());
        static::assertSame(2, $field->getTabOrder());
        static::assertSame(3, $field->getSectionOrder());
        static::assertSame(4, $field->getBlockOrder());
        static::assertSame(['componentName' => 'sw-colorpicker'], $field->getCustom());
        static::assertFalse($field->getScss());
        static::assertTrue($field->getFullWidth());
    }

    public function testGetLabelThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Shopware\Storefront\Theme\ThemeConfigField::getLabel()" is deprecated and will be removed in v6.8.0.0. Use "getLabelSnippetKey" instead.'
        ));

        (new ThemeConfigField())->getLabel();
    }

    public function testSetLabelThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Shopware\Storefront\Theme\ThemeConfigField::setLabel()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeConfigField())->setLabel(['fields' => ['sw-color-brand-primary' => 'Primary colour']]);
    }

    public function testGetHelpTextThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Shopware\Storefront\Theme\ThemeConfigField::getHelpText()" is deprecated and will be removed in v6.8.0.0. Use "getHelpTextSnippetKey" instead.'
        ));

        (new ThemeConfigField())->getHelpText();
    }

    public function testSetHelpTextThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Shopware\Storefront\Theme\ThemeConfigField::setHelpText()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new ThemeConfigField())->setHelpText(['en-GB' => ['label' => 'The main colour']]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLabelAndHelpTextRoundTripWhenTheFeatureIsInactive(): void
    {
        $field = new ThemeConfigField();

        $field->setLabel(['fields' => ['sw-color-brand-primary' => 'Primary colour']]);
        $field->setHelpText(['en-GB' => ['label' => 'The main colour']]);

        static::assertSame(['fields' => ['sw-color-brand-primary' => 'Primary colour']], $field->getLabel());
        static::assertSame(['en-GB' => ['label' => 'The main colour']], $field->getHelpText());
    }
}
