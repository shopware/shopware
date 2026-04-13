<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\TextAreaField;

/**
 * @internal
 */
#[CoversClass(TextAreaField::class)]
class TextAreaFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/text-area-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $textAreaField = $customFieldSet->getFields()[0];

        static::assertInstanceOf(TextAreaField::class, $textAreaField);
        static::assertSame('test_text_area_field', $textAreaField->getName());
        static::assertSame([
            'en-GB' => 'Test text-area field',
        ], $textAreaField->getLabel());
        static::assertSame([], $textAreaField->getHelpText());
        static::assertSame(['en-GB' => 'Enter a text...'], $textAreaField->getPlaceholder());
        static::assertSame(1, $textAreaField->getPosition());
        static::assertFalse($textAreaField->getRequired());
    }
}
