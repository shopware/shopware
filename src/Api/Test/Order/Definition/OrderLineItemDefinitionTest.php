<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Order\Definition\OrderLineItemDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class OrderLineItemDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderLineItemDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'orderId', 'identifier', 'quantity', 'unitPrice', 'totalPrice', 'payload'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderLineItemDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['orderDeliveryPositions'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderLineItemDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
