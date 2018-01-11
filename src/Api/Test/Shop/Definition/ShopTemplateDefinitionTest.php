<?php declare(strict_types=1);

namespace Shopware\Api\Test\Shop\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Shop\Definition\ShopTemplateDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ShopTemplateDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ShopTemplateDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'template', 'name', 'emotion'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ShopTemplateDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['children', 'configForms', 'configFormFields', 'configPresets'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ShopTemplateDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['shops'], $fields->getKeys());
    }
}
