<?php declare(strict_types=1);

namespace Shopware\Api\Test\Country\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Definition\CountryTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CountryTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CountryTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['countryId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CountryTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CountryTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
