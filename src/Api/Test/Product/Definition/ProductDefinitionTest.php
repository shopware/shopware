<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Product\Definition\ProductDefinition;

class ProductDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['listingPrices', 'media', 'prices', 'searchKeywords', 'translations', 'categories', 'seoCategories', 'tabs', 'streams'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
