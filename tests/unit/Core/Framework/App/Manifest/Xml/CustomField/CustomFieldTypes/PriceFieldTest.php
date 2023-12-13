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
        static::assertEquals('test_price_field', $priceField->getName());
        static::assertEquals([
            'en-GB' => 'Test price field',
        ], $priceField->getLabel());
        static::assertEquals([], $priceField->getHelpText());
        static::assertEquals(1, $priceField->getPosition());
        static::assertFalse($priceField->getRequired());
    }
}
