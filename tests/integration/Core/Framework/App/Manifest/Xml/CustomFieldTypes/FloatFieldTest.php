<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class FloatFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $floatField = $this->importCustomField(__DIR__ . '/_fixtures/float-field.xml');

        static::assertSame('test_float_field', $floatField->getName());
        static::assertSame('float', $floatField->getType());
        static::assertTrue($floatField->isActive());
        static::assertEquals([
            'type' => 'number',
            'label' => [
                'en-GB' => 'Test float field',
                'de-DE' => 'Test Kommazahlenfeld',
            ],
            'helpText' => [
                'en-GB' => 'This is an float field.',
            ],
            'placeholder' => [
                'en-GB' => 'Enter an float...',
            ],
            'componentName' => 'sw-field',
            'customFieldType' => 'number',
            'customFieldPosition' => 2,
            'numberType' => 'float',
            'min' => 0.5,
            'max' => 1.6,
            'step' => 2.2,
        ], $floatField->getConfig());
    }
}
