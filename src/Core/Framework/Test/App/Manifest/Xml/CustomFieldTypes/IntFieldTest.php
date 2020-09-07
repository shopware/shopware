<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\IntField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class IntFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/int-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $intField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(IntField::class, $intField);
        static::assertEquals('test_int_field', $intField->getName());
        static::assertEquals([
            'en-GB' => 'Test int field',
            'de-DE' => 'Test Ganzzahlenfeld',
        ], $intField->getLabel());
        static::assertEquals(['en-GB' => 'This is an int field.'], $intField->getHelpText());
        static::assertEquals(1, $intField->getPosition());
        static::assertEquals(2, $intField->getSteps());
        static::assertEquals(0, $intField->getMin());
        static::assertEquals(1, $intField->getMax());
        static::assertEquals(['en-GB' => 'Enter an int...'], $intField->getPlaceholder());
        static::assertTrue($intField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $intField = $this->importCustomField(__DIR__ . '/_fixtures/int-field.xml');

        static::assertEquals('test_int_field', $intField->getName());
        static::assertEquals('int', $intField->getType());
        static::assertTrue($intField->isActive());
        static::assertEquals([
            'type' => 'number',
            'label' => [
                'en-GB' => 'Test int field',
                'de-DE' => 'Test Ganzzahlenfeld',
            ],
            'helpText' => [
                'en-GB' => 'This is an int field.',
            ],
            'placeholder' => [
                'en-GB' => 'Enter an int...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'number',
            'customFieldPosition' => 1,
            'numberType' => 'int',
            'min' => 0,
            'max' => 1,
            'step' => 2,
            'validation' => 'required',
        ], $intField->getConfig());
    }
}
