<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\SingleEntitySelectField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class SingleEntitySelectFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/single-entity-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $singleEntitySelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(SingleEntitySelectField::class, $singleEntitySelectField);
        static::assertEquals('test_single_entity_select_field', $singleEntitySelectField->getName());
        static::assertEquals([
            'en-GB' => 'Test single-entity-select field',
        ], $singleEntitySelectField->getLabel());
        static::assertEquals([], $singleEntitySelectField->getHelpText());
        static::assertEquals(1, $singleEntitySelectField->getPosition());
        static::assertEquals(['en-GB' => 'Choose an entity...'], $singleEntitySelectField->getPlaceholder());
        static::assertFalse($singleEntitySelectField->getRequired());
        static::assertEquals('product', $singleEntitySelectField->getEntity());
    }

    public function testToEntityArray(): void
    {
        $singleEntitySelectField = $this->importCustomField(__DIR__ . '/_fixtures/single-entity-select-field.xml');

        static::assertEquals('test_single_entity_select_field', $singleEntitySelectField->getName());
        static::assertEquals('entity', $singleEntitySelectField->getType());
        static::assertTrue($singleEntitySelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test single-entity-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose an entity...',
            ],
            'componentName' => 'sw-entity-single-select',
            'customFieldType' => 'select',
            'customFieldPosition' => 1,
            'entity' => 'product',
        ], $singleEntitySelectField->getConfig());
    }
}
