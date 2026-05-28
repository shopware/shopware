<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\ColorPickerField;

/**
 * @internal
 */
#[CoversClass(ColorPickerField::class)]
class ColorPickerFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/color-picker-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $colorPickerField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(ColorPickerField::class, $colorPickerField);
        static::assertSame('test_color_picker_field', $colorPickerField->getName());
        static::assertSame([
            'en-GB' => 'Test color-picker field',
        ], $colorPickerField->getLabel());
        static::assertSame([], $colorPickerField->getHelpText());
        static::assertSame(1, $colorPickerField->getPosition());
        static::assertFalse($colorPickerField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/color-picker-field.xml');
        static::assertNotNull($manifest->getCustomFields());

        $colorPickerField = $manifest->getCustomFields()->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(ColorPickerField::class, $colorPickerField);

        static::assertEquals([
            'name' => 'test_color_picker_field',
            'type' => 'text',
            'config' => [
                'label' => [
                    'en-GB' => 'Test color-picker field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'colorpicker',
                'componentName' => 'sw-field',
                'customFieldType' => 'colorpicker',
            ],
        ], $colorPickerField->toEntityPayload());
    }
}
