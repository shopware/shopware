<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\FloatField;

/**
 * @internal
 */
#[CoversClass(FloatField::class)]
class FloatFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/float-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $floatField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(FloatField::class, $floatField);
        static::assertSame('test_float_field', $floatField->getName());
        static::assertSame([
            'en-GB' => 'Test float field',
            'de-DE' => 'Test Kommazahlenfeld',
        ], $floatField->getLabel());
        static::assertSame(['en-GB' => 'This is a float field.'], $floatField->getHelpText());
        static::assertSame(2, $floatField->getPosition());
        static::assertSame(2.2, $floatField->getSteps());
        static::assertSame(0.5, $floatField->getMin());
        static::assertSame(1.6, $floatField->getMax());
        static::assertSame(['en-GB' => 'Enter a float...'], $floatField->getPlaceholder());
        static::assertFalse($floatField->getRequired());
    }
}
