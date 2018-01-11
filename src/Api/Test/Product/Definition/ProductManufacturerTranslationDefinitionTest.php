<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Product\Definition\ProductManufacturerTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ProductManufacturerTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ProductManufacturerTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['productManufacturerId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ProductManufacturerTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ProductManufacturerTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
