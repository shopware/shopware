<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\ColorPickerField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ColorPickerFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/color-picker-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $colorPickerField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(ColorPickerField::class, $colorPickerField);
        static::assertEquals('test_color_picker_field', $colorPickerField->getName());
        static::assertEquals([
            'en-GB' => 'Test color-picker field',
        ], $colorPickerField->getLabel());
        static::assertEquals([], $colorPickerField->getHelpText());
        static::assertEquals(1, $colorPickerField->getPosition());
        static::assertFalse($colorPickerField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $colorPickerField = $this->importCustomField(__DIR__ . '/_fixtures/color-picker-field.xml');

        static::assertEquals('test_color_picker_field', $colorPickerField->getName());
        static::assertEquals('text', $colorPickerField->getType());
        static::assertTrue($colorPickerField->isActive());
        static::assertEquals([
            'type' => 'colorpicker',
            'label' => [
                'en-GB' => 'Test color-picker field',
            ],
            'helpText' => [],
            'componentName' => 'sw-field',
            'customFieldType' => 'colorpicker',
            'customFieldPosition' => 1,
        ], $colorPickerField->getConfig());
    }
}
