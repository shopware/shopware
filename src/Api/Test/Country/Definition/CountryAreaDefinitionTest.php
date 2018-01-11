<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Definition\CountryAreaDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CountryAreaDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CountryAreaDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CountryAreaDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations', 'taxAreaRules'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CountryAreaDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
