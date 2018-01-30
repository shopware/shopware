<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Product\Definition\ProductSearchKeywordDefinition;

class ProductSearchKeywordDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductSearchKeywordDefinition::getFields()->filterByFlag(Required::class);
        $keys = $fields->getKeys();
        sort($keys);

        $this->assertEquals(
            ['id', 'keyword', 'productId', 'ranking', 'shopId'],
            $keys
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductSearchKeywordDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductSearchKeywordDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
