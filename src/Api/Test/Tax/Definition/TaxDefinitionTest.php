<?php declare(strict_types=1);

namespace Shopware\Api\Test\Tax\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class TaxDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = TaxDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'rate', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = TaxDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['areaRules'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = TaxDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['products'], $fields->getKeys());
    }
}
