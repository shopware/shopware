<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class BoolFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $boolField = $this->importCustomField(__DIR__ . '/_fixtures/bool-field.xml');

        static::assertSame('test_bool_field', $boolField->getName());
        static::assertSame('bool', $boolField->getType());
        static::assertTrue($boolField->isActive());
        static::assertEquals([
            'type' => 'checkbox',
            'label' => [
                'en-GB' => 'Test bool field',
            ],
            'helpText' => [],
            'componentName' => 'sw-field',
            'customFieldType' => 'checkbox',
            'customFieldPosition' => 1,
        ], $boolField->getConfig());
    }
}
