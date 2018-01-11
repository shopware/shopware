<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Definition\ProductListingPriceDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ProductListingPriceDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductListingPriceDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'productId', 'customerGroupId', 'sortingPrice', 'price', 'displayFromPrice'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductListingPriceDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductListingPriceDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
