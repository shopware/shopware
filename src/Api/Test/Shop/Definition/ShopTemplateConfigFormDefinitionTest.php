<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ShopTemplateConfigFormDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopTemplateConfigFormDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'shopTemplateId', 'type', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopTemplateConfigFormDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['children', 'fields'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopTemplateConfigFormDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
