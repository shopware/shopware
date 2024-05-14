<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiSelectField;

/**
 * @internal
 */
#[CoversClass(MultiSelectField::class)]
class MultiSelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/multi-select-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiSelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);
        static::assertEquals('test_multi_select_field', $multiSelectField->getName());
        static::assertEquals([
            'en-GB' => 'Test multi-select field',
        ], $multiSelectField->getLabel());
        static::assertEquals([], $multiSelectField->getHelpText());
        static::assertEquals(1, $multiSelectField->getPosition());
        static::assertEquals(['en-GB' => 'Choose your options...'], $multiSelectField->getPlaceholder());
        static::assertFalse($multiSelectField->getRequired());
        static::assertEquals([
            'first' => [
                'en-GB' => 'First',
                'de-DE' => 'Erster',
            ],
            'second' => [
                'en-GB' => 'Second',
            ],
        ], $multiSelectField->getOptions());
    }
}
