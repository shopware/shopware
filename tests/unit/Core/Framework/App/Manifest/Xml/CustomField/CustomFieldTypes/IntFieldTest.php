<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\IntField;

/**
 * @internal
 */
#[CoversClass(IntField::class)]
class IntFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/int-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

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
}
