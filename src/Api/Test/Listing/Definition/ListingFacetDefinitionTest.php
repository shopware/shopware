<?php declare(strict_types=1);

namespace Shopware\Api\Test\Listing\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Listing\Definition\ListingFacetDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class ListingFacetDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ListingFacetDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'uniqueKey', 'payload', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = ListingFacetDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = ListingFacetDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
