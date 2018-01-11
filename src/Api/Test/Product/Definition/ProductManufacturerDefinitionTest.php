<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Definition\ProductManufacturerDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ProductManufacturerDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductManufacturerDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductManufacturerDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductManufacturerDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['products'], $fields->getKeys());
    }
}
