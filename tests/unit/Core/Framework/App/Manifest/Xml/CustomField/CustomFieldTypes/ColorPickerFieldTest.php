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
        static::assertEquals('test_color_picker_field', $colorPickerField->getName());
        static::assertEquals([
            'en-GB' => 'Test color-picker field',
        ], $colorPickerField->getLabel());
        static::assertEquals([], $colorPickerField->getHelpText());
        static::assertEquals(1, $colorPickerField->getPosition());
        static::assertFalse($colorPickerField->getRequired());
    }
}
