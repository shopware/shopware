<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\TextField;

/**
 * @internal
 */
#[CoversClass(TextField::class)]
class TextFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/text-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

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
}
