<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Definition\CountryStateTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CountryStateTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CountryStateTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['countryStateId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CountryStateTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CountryStateTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
