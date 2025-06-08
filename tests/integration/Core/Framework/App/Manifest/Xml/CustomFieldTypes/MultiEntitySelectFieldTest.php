<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class MultiEntitySelectFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $multiEntitySelectField = $this->importCustomField(__DIR__ . '/_fixtures/multi-entity-select-field.xml');

        static::assertSame('test_multi_entity_select_field', $multiEntitySelectField->getName());
        static::assertSame('entity', $multiEntitySelectField->getType());
        static::assertTrue($multiEntitySelectField->isActive());
        static::assertEquals([
            'label' => [
                'en-GB' => 'Test multi-entity-select field',
            ],
            'helpText' => [],
            'placeholder' => [
                'en-GB' => 'Choose an entity...',
            ],
            'componentName' => 'sw-entity-multi-id-select',
            'customFieldType' => 'select',
            'customFieldPosition' => 1,
            'entity' => 'product',
        ], $multiEntitySelectField->getConfig());
    }
}
