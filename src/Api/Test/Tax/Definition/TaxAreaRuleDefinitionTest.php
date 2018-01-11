<?php declare(strict_types=1);

namespace Shopware\Api\Test\Tax\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class TaxAreaRuleDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = TaxAreaRuleDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'taxId', 'customerGroupId', 'taxRate', 'name', 'translations'],
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
