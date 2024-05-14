<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiEntitySelectField;

/**
 * @internal
 */
#[CoversClass(MultiEntitySelectField::class)]
class MultiEntitySelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-entity-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiEntitySelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiEntitySelectField::class, $multiEntitySelectField);
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
}
