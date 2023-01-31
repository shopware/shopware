<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\PriceField;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\CustomFieldTypeTestBehaviour;

/**
 * @internal
 */
class PriceFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomFieldTypeTestBehaviour;

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/price-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $priceField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(PriceField::class, $priceField);
        static::assertEquals('test_price_field', $priceField->getName());
        static::assertEquals([
            'en-GB' => 'Test price field',
        ], $priceField->getLabel());
        static::assertEquals([], $priceField->getHelpText());
        static::assertEquals(1, $priceField->getPosition());
        static::assertFalse($priceField->getRequired());
    }

    public function testToEntityArray(): void
    {
        $priceField = $this->importCustomField(__DIR__ . '/_fixtures/price-field.xml');

        static::assertEquals('test_price_field', $priceField->getName());
        static::assertEquals('price', $priceField->getType());
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
