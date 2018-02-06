<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CustomerGroupDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CustomerGroupDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CustomerGroupDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['discounts', 'translations', 'taxAreaRules'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CustomerGroupDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['customers', 'shops'], $fields->getKeys());
    }
}
