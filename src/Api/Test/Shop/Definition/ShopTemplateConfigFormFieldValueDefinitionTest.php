<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldValueDefinition;

class ShopTemplateConfigFormFieldValueDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopTemplateConfigFormFieldValueDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'shopTemplateConfigFormFieldId', 'shopId', 'value'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopTemplateConfigFormFieldValueDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopTemplateConfigFormFieldValueDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
