<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Field\PriceRulesField;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Product\Definition\ProductDefinition;

class ProductDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'manufacturerId', 'taxId', 'price', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['children', 'media', 'categories', 'seoCategories', 'tabs', 'streams', 'searchKeywords', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testFieldsDefinedAsInherited()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(Inherited::class);
        $this->assertEquals(
            ['manufacturerId', 'unitId', 'taxId', 'price', 'supplierNumber', 'ean', 'isCloseout', 'minStock', 'purchaseSteps', 'maxPurchase', 'minPurchase', 'purchaseUnit', 'referenceUnit', 'shippingFree', 'purchasePrice', 'pseudoSales', 'markAsTopseller', 'sales', 'position', 'weight', 'width', 'height', 'length', 'template', 'allowNotification', 'releaseDate', 'priceGroupId', 'categoryTree', 'prices', 'additionalText', 'name', 'keywords', 'description', 'descriptionLong', 'metaTitle', 'packUnit', 'tax', 'manufacturer', 'unit', 'media', 'categories', 'translations'],
            $fields->getKeys()
        );
    }

    public function testPricesFieldIsDefinesAsPriceRuleField()
    {
        $field = ProductDefinition::getFields()->get('prices');
        $this->assertInstanceOf(PriceRulesField::class, $field);
    }
}
