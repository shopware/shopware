<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Definition\ProductMediaDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ProductMediaDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductMediaDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'productId', 'mediaId', 'isCover'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductMediaDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductMediaDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
