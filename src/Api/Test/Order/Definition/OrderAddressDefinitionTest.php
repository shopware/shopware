<?php declare(strict_types=1);

namespace Shopware\Api\Test\Order\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Order\Definition\OrderAddressDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class OrderAddressDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = OrderAddressDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'countryId', 'salutation', 'firstName', 'lastName', 'street', 'zipcode', 'city'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = OrderAddressDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = OrderAddressDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['orders', 'orderDeliveries'], $fields->getKeys());
    }
}
