<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CountryDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CountryDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id',  'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CountryDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['states', 'translations', 'taxAreaRules'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CountryDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(
            ['customerAddresses', 'orderAddresses', 'shops'],
            $fields->getKeys()
        );
    }
}
