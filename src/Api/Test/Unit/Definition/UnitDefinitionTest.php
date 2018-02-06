<?php declare(strict_types=1);

namespace Shopware\Api\Test\Unit\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Unit\Definition\UnitDefinition;

class UnitDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = UnitDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = UnitDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = UnitDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['products'], $fields->getKeys());
    }
}
