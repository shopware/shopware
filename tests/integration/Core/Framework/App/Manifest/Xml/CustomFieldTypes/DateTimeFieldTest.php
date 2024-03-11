<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class DateTimeFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $dateTimeField = $this->importCustomField(__DIR__ . '/_fixtures/date-time-field.xml');

        static::assertSame('test_datetime_field', $dateTimeField->getName());
        static::assertSame('datetime', $dateTimeField->getType());
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
