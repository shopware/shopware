<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\PriceField;

/**
 * @internal
 */
#[CoversClass(PriceField::class)]
class PriceFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/price-field.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $priceField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(PriceField::class, $priceField);
        static::assertSame('test_price_field', $priceField->getName());
        static::assertSame([
            'en-GB' => 'Test price field',
        ], $priceField->getLabel());
        static::assertSame([], $priceField->getHelpText());
        static::assertSame(1, $priceField->getPosition());
        static::assertFalse($priceField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/price-field.xml');
        static::assertNotNull($manifest->getCustomFields());

        $priceField = $manifest->getCustomFields()->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(PriceField::class, $priceField);

        static::assertEquals([
            'name' => 'test_price_field',
            'type' => 'price',
            'config' => [
                'label' => [
                    'en-GB' => 'Test price field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'price',
                'componentName' => 'sw-price-field',
                'customFieldType' => 'price',
            ],
        ], $priceField->toEntityPayload());
    }
}
