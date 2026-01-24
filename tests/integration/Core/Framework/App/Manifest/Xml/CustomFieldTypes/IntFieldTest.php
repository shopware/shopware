<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class IntFieldTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use CustomFieldTypeTestBehaviour;

    public function testToEntityArray(): void
    {
        $intField = $this->importCustomField(__DIR__ . '/_fixtures/int-field.xml');

        static::assertSame('test_int_field', $intField->getName());
        static::assertSame('int', $intField->getType());
        static::assertTrue($intField->isActive());
        static::assertEquals([
            'type' => 'number',
            'label' => [
                'en-GB' => 'Test int field',
                'de-DE' => 'Test Ganzzahlenfeld',
            ],
            'helpText' => [
                'en-GB' => 'This is an int field.',
            ],
            'placeholder' => [
                'en-GB' => 'Enter an int...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'number',
            'customFieldPosition' => 1,
            'numberType' => 'int',
            'min' => 0,
            'max' => 1,
            'step' => 2,
            'validation' => 'required',
        ], $intField->getConfig());
    }
}
