<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\TextField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TextFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/text-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $textField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(TextField::class, $textField);
        static::assertEquals('test_text_field', $textField->getName());
        static::assertEquals([
            'en-GB' => 'Test text field',
        ], $textField->getLabel());
        static::assertEquals([], $textField->getHelpText());
        static::assertEquals(1, $textField->getPosition());
        static::assertEquals(['en-GB' => 'Enter a text...'], $textField->getPlaceholder());
        static::assertFalse($textField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $textField = $this->importCustomField(__DIR__ . '/_fixtures/text-field.xml');

        static::assertEquals('test_text_field', $textField->getName());
        static::assertEquals('text', $textField->getType());
        static::assertTrue($textField->isActive());
        static::assertEquals([
            'type' => 'text',
            'label' => [
                'en-GB' => 'Test text field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Enter a text...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'text',
            'customFieldPosition' => 1,
        ], $textField->getConfig());
    }
}
