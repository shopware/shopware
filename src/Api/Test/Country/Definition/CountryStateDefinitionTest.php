<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Definition\CountryStateDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CountryStateDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CountryStateDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'countryId', 'shortCode', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CountryStateDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations', 'taxAreaRules'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CountryStateDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
