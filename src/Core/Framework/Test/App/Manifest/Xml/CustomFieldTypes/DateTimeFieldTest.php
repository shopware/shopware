<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\DateTimeField;
use Shopware\Core\Framework\Test\App\CustomFieldTypeTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DateTimeFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/date-time-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $dateTimeField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(DateTimeField::class, $dateTimeField);
        static::assertEquals('test_datetime_field', $dateTimeField->getName());
        static::assertEquals([
            'en-GB' => 'Test datetime field',
        ], $dateTimeField->getLabel());
        static::assertEquals([], $dateTimeField->getHelpText());
        static::assertEquals(1, $dateTimeField->getPosition());
        static::assertFalse($dateTimeField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $dateTimeField = $this->importCustomField(__DIR__ . '/_fixtures/date-time-field.xml');

        static::assertEquals('test_datetime_field', $dateTimeField->getName());
        static::assertEquals('datetime', $dateTimeField->getType());
        static::assertTrue($dateTimeField->isActive());
        static::assertEquals([
            'type' => 'date',
            'label' => [
                'en-GB' => 'Test datetime field',
            ],
            'helpText' => [],
            'componentName' => 'sw-field',
            'customFieldType' => 'date',
            'customFieldPosition' => 1,
            'config' => [
                'time_24hr' => true,
            ],
            'dateType' => 'datetime',
        ], $dateTimeField->getConfig());
    }
}
