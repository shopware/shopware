<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class SingleSelectFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $singleSelectField = $this->importCustomField(__DIR__ . '/_fixtures/single-select-field.xml');

        static::assertSame('test_single_select_field', $singleSelectField->getName());
        static::assertSame('select', $singleSelectField->getType());
        static::assertTrue($singleSelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test single-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose an option...',
            ],
            'componentName' => 'sw-single-select',
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
        ], $singleSelectField->getConfig());
    }
}
