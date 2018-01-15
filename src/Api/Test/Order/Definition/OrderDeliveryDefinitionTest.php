<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Order\Definition\OrderDeliveryDefinition;

class OrderDeliveryDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderDeliveryDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'orderId', 'shippingAddressId', 'orderStateId', 'shippingMethodId', 'shippingDateEarliest', 'shippingDateLatest', 'payload'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderDeliveryDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['positions'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderDeliveryDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
