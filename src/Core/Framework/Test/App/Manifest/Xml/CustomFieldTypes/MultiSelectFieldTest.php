<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\MultiSelectField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MultiSelectFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiSelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);
        static::assertEquals('test_multi_select_field', $multiSelectField->getName());
        static::assertEquals([
            'en-GB' => 'Test multi-select field',
        ], $multiSelectField->getLabel());
        static::assertEquals([], $multiSelectField->getHelpText());
        static::assertEquals(1, $multiSelectField->getPosition());
        static::assertEquals(['en-GB' => 'Choose your options...'], $multiSelectField->getPlaceholder());
        static::assertFalse($multiSelectField->getRequired());
        static::assertEquals([
            'first' => [
                'en-GB' => 'First',
                'de-DE' => 'Erster',
            ],
            'second' => [
                'en-GB' => 'Second',
            ],
        ], $multiSelectField->getOptions());
    }

    public function testToEntityArray(): void
    {
        $multiSelectField = $this->importCustomField(__DIR__ . '/_fixtures/multi-select-field.xml');

        static::assertEquals('test_multi_select_field', $multiSelectField->getName());
        static::assertEquals('select', $multiSelectField->getType());
        static::assertTrue($multiSelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test multi-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose your options...',
            ],
            'componentName' => 'sw-multi-select',
            'customFieldType' => 'select',
            'customFieldPosition' => 1,
            'options' => [
                [
                    'label' => [
                        'en-GB' => 'First',
                        'de-DE' => 'Erster',
                    ],
                    'value' => 'first',
                ],
                [
                    'label' => [
                        'en-GB' => 'Second',
                    ],
                    'value' => 'second',
                ],
            ],
        ], $multiSelectField->getConfig());
    }
}
