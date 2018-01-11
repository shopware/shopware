<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Definition\CustomerGroupDiscountDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CustomerGroupDiscountDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CustomerGroupDiscountDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'customerGroupId', 'percentageDiscount', 'minimumCartAmount'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CustomerGroupDiscountDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CustomerGroupDiscountDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
