<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Shop\Definition\ShopTemplateConfigPresetDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ShopTemplateConfigPresetDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopTemplateConfigPresetDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'shopTemplateId', 'name', 'description', 'elementValues'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopTemplateConfigPresetDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopTemplateConfigPresetDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
