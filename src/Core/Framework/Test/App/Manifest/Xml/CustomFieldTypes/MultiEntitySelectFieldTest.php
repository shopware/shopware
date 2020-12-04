<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\SingleEntitySelectField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MultiEntitySelectFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-entity-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiEntitySelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(SingleEntitySelectField::class, $multiEntitySelectField);
        static::assertEquals('test_multi_entity_select_field', $multiEntitySelectField->getName());
        static::assertEquals([
            'en-GB' => 'Test multi-entity-select field',
        ], $multiEntitySelectField->getLabel());
        static::assertEquals([], $multiEntitySelectField->getHelpText());
        static::assertEquals(1, $multiEntitySelectField->getPosition());
        static::assertEquals(['en-GB' => 'Choose an entity...'], $multiEntitySelectField->getPlaceholder());
        static::assertFalse($multiEntitySelectField->getRequired());
        static::assertEquals('product', $multiEntitySelectField->getEntity());
    }

    public function testToEntityArray(): void
    {
        $multiEntitySelectField = $this->importCustomField(__DIR__ . '/_fixtures/multi-entity-select-field.xml');

        static::assertEquals('test_multi_entity_select_field', $multiEntitySelectField->getName());
        static::assertEquals('entity', $multiEntitySelectField->getType());
        static::assertTrue($multiEntitySelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test multi-entity-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose an entity...',
            ],
            'componentName' => 'sw-entity-multi-id-select',
            'customFieldType' => 'select',
            'customFieldPosition' => 1,
            'entity' => 'product',
        ], $multiEntitySelectField->getConfig());
    }
}
