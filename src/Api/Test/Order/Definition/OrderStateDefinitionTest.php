<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Order\Definition\OrderStateDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class OrderStateDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderStateDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name', 'description', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderStateDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderStateDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['orders', 'orderDeliveries'], $fields->getKeys());
    }
}
