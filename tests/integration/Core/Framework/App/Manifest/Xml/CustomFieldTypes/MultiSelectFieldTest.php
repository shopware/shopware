<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class MultiSelectFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $multiSelectField = $this->importCustomField(__DIR__ . '/_fixtures/multi-select-field.xml');

        static::assertSame('test_multi_select_field', $multiSelectField->getName());
        static::assertSame('select', $multiSelectField->getType());
        static::assertTrue($multiSelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test multi-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose your options...',
            ],
            'componentName' => 'sw-multi-select',
            'customFieldType' => 'select',
            'customFieldPosition' => 1,
            'options' => [
                [
                    'label' => [
                        'en-GB' => 'First',
                        'de-DE' => 'Erster',
                    ],
                    'value' => 'first',
                ],
                [
                    'label' => [
                        'en-GB' => 'Second',
                    ],
                    'value' => 'second',
                ],
            ],
        ], $multiSelectField->getConfig());
    }
}
