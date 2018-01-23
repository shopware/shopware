<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Shop\Definition\ShopDefinition;

class ShopDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'templateId', 'documentTemplateId', 'categoryId', 'localeId', 'currencyId', 'customerGroupId', 'paymentMethodId', 'shippingMethodId', 'countryId', 'name', 'position', 'host', 'basePath', 'baseUrl'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['configFormFieldValues', 'productSearchKeywords', 'seoUrls', 'children', 'templateConfigFormFieldValues', 'snippets', 'productSeoCategories', 'currencies'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['customers', 'orders'], $fields->getKeys());
    }
}
