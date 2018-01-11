<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Definition\ProductStreamDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ProductStreamDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductStreamDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductStreamDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['productTabs', 'products'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductStreamDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
