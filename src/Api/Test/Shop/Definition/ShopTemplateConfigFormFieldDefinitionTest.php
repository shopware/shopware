<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ShopTemplateConfigFormFieldDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopTemplateConfigFormFieldDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id','shopTemplateId','shopTemplateConfigFormId','type','name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopTemplateConfigFormFieldDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['values'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopTemplateConfigFormFieldDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
