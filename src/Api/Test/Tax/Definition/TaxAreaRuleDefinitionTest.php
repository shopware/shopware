<?php declare(strict_types=1);

namespace Shopware\Api\Test\Tax\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;

class TaxAreaRuleDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = TaxAreaRuleDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'taxId', 'customerGroupId', 'taxRate','translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = TaxAreaRuleDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = TaxAreaRuleDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
