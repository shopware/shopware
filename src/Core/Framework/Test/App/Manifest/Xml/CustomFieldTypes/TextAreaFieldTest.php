<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\TextAreaField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TextAreaFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/text-area-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $textAreaField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(TextAreaField::class, $textAreaField);
        static::assertEquals('test_text_area_field', $textAreaField->getName());
        static::assertEquals([
            'en-GB' => 'Test text-area field',
        ], $textAreaField->getLabel());
        static::assertEquals([], $textAreaField->getHelpText());
        static::assertEquals(['en-GB' => 'Enter a text...'], $textAreaField->getPlaceholder());
        static::assertEquals(1, $textAreaField->getPosition());
        static::assertFalse($textAreaField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $textAreaField = $this->importCustomField(__DIR__ . '/_fixtures/text-area-field.xml');

        static::assertEquals('test_text_area_field', $textAreaField->getName());
        static::assertEquals('html', $textAreaField->getType());
        static::assertTrue($textAreaField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test text-area field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Enter a text...',
            ],
            'componentName' => 'sw-text-editor',
            'customFieldType' => 'textEditor',
            'customFieldPosition' => 1,
        ], $textAreaField->getConfig());
    }
}
