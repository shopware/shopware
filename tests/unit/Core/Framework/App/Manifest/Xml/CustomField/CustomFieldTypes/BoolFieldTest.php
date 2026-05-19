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
        static::assertSame('test_bool_field', $boolField->getName());
        static::assertSame([
            'en-GB' => 'Test bool field',
        ], $boolField->getLabel());
        static::assertSame([], $boolField->getHelpText());
        static::assertSame(1, $boolField->getPosition());
        static::assertFalse($boolField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/bool-field.xml');
        static::assertNotNull($manifest->getCustomFields());

        $boolField = $manifest->getCustomFields()->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(BoolField::class, $boolField);

        static::assertEquals([
            'name' => 'test_bool_field',
            'type' => 'bool',
            'config' => [
                'label' => [
                    'en-GB' => 'Test bool field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'checkbox',
                'componentName' => 'sw-field',
                'customFieldType' => 'checkbox',
            ],
        ], $boolField->toEntityPayload());
    }
}
