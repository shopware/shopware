<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Order\Definition\OrderDeliveryPositionDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class OrderDeliveryPositionDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderDeliveryPositionDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'orderDeliveryId', 'orderLineItemId', 'unitPrice', 'totalPrice', 'quantity', 'payload'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderDeliveryPositionDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderDeliveryPositionDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
