<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\FloatField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FloatFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/float-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $floatField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(FloatField::class, $floatField);
        static::assertEquals('test_float_field', $floatField->getName());
        static::assertEquals([
            'en-GB' => 'Test float field',
            'de-DE' => 'Test Kommazahlenfeld',
        ], $floatField->getLabel());
        static::assertEquals(['en-GB' => 'This is an float field.'], $floatField->getHelpText());
        static::assertEquals(2, $floatField->getPosition());
        static::assertEquals(2.2, $floatField->getSteps());
        static::assertEquals(0.5, $floatField->getMin());
        static::assertEquals(1.6, $floatField->getMax());
        static::assertEquals(['en-GB' => 'Enter an float...'], $floatField->getPlaceholder());
        static::assertFalse($floatField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $floatField = $this->importCustomField(__DIR__ . '/_fixtures/float-field.xml');

        static::assertEquals('test_float_field', $floatField->getName());
        static::assertEquals('float', $floatField->getType());
        static::assertTrue($floatField->isActive());
        static::assertEquals([
            'type' => 'number',
            'label' => [
                'en-GB' => 'Test float field',
                'de-DE' => 'Test Kommazahlenfeld',
            ],
            'helpText' => [
                'en-GB' => 'This is an float field.',
            ],
            'placeholder' => [
                'en-GB' => 'Enter an float...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'number',
            'customFieldPosition' => 2,
            'numberType' => 'float',
            'min' => 0.5,
            'max' => 1.6,
            'step' => 2.2,
        ], $floatField->getConfig());
    }
}
