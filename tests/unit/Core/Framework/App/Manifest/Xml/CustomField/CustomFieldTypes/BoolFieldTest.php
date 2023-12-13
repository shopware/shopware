<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\BoolField;

/**
 * @internal
 */
#[CoversClass(BoolField::class)]
class BoolFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/bool-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $boolField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(BoolField::class, $boolField);
        static::assertEquals('test_bool_field', $boolField->getName());
        static::assertEquals([
            'en-GB' => 'Test bool field',
        ], $boolField->getLabel());
        static::assertEquals([], $boolField->getHelpText());
        static::assertEquals(1, $boolField->getPosition());
        static::assertFalse($boolField->getRequired());
    }
}
