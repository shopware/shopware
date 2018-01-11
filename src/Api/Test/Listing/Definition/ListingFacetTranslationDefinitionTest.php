<?php declare(strict_types=1);

namespace Shopware\Api\Test\Listing\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ListingFacetTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ListingFacetTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['listingFacetId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ListingFacetTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ListingFacetTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
