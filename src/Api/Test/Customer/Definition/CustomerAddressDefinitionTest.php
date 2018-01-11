<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Definition\CustomerAddressDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CustomerAddressDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CustomerAddressDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'customerId', 'countryId', 'salutation', 'firstName', 'lastName', 'zipcode', 'city'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CustomerAddressDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CustomerAddressDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
