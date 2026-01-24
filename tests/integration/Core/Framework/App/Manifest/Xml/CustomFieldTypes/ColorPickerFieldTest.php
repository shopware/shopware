<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class ColorPickerFieldTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use CustomFieldTypeTestBehaviour;

    public function testToEntityArray(): void
    {
        $colorPickerField = $this->importCustomField(__DIR__ . '/_fixtures/color-picker-field.xml');

        static::assertSame('test_color_picker_field', $colorPickerField->getName());
        static::assertSame('text', $colorPickerField->getType());
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
