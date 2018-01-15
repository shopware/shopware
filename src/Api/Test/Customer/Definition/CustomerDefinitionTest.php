<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CustomerDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CustomerDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'groupId', 'defaultPaymentMethodId', 'shopId', 'defaultBillingAddressId', 'defaultShippingAddressId', 'number', 'salutation', 'firstName', 'lastName', 'password', 'email'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CustomerDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['addresses'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CustomerDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['orders'], $fields->getKeys());
    }
}
