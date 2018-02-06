<?php declare(strict_types=1);

namespace Shopware\Api\Test\Listing\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Listing\Definition\ListingFacetDefinition;

class ListingFacetDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = ListingFacetDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'uniqueKey', 'payload', 'translations'],
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
