<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class PriceFieldTest extends TestCase
{
    use CustomFieldTypeTestBehaviour;
    use IntegrationTestBehaviour;

    public function testToEntityArray(): void
    {
        $priceField = $this->importCustomField(__DIR__ . '/_fixtures/price-field.xml');

        static::assertSame('test_price_field', $priceField->getName());
        static::assertSame('price', $priceField->getType());
        static::assertTrue($priceField->isActive());
        static::assertEquals([
            'type' => 'price',
            'label' => [
                'en-GB' => 'Test price field',
            ],
            'helpText' => [],
            'componentName' => 'sw-price-field',
            'customFieldType' => 'price',
            'customFieldPosition' => 1,
        ], $priceField->getConfig());
    }
}
