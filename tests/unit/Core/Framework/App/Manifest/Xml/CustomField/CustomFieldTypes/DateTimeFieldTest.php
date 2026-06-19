<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\DateTimeField;

/**
 * @internal
 */
#[CoversClass(DateTimeField::class)]
class DateTimeFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/date-time-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $dateTimeField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(DateTimeField::class, $dateTimeField);
        static::assertSame('test_datetime_field', $dateTimeField->getName());
        static::assertSame([
            'en-GB' => 'Test datetime field',
        ], $dateTimeField->getLabel());
        static::assertSame([], $dateTimeField->getHelpText());
        static::assertSame(1, $dateTimeField->getPosition());
        static::assertFalse($dateTimeField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/date-time-field.xml');
        static::assertNotNull($manifest->getCustomFields());

        $dateTimeField = $manifest->getCustomFields()->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(DateTimeField::class, $dateTimeField);

        static::assertEquals([
            'name' => 'test_datetime_field',
            'type' => 'datetime',
            'config' => [
                'label' => [
                    'en-GB' => 'Test datetime field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'date',
                'componentName' => 'sw-field',
                'customFieldType' => 'date',
                'config' => [
                    'time_24hr' => true,
                ],
                'dateType' => 'datetime',
            ],
        ], $dateTimeField->toEntityPayload());
    }
}
