<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\SalesChannel\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingStruct::class)]
class ProductListingStructTest extends TestCase
{
    public function testSetListingAcceptsProductListingResult(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        $struct = new ProductListingStruct();
        $struct->setListing($listing);

        static::assertSame($listing, $struct->getListing());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the runtime check in setListing(); the narrowed native type enforces this
     */
    public function testSetListingWithPlainSearchResultIsDeprecated(): void
    {
        $struct = new ProductListingStruct();

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing a plain "EntitySearchResult" to "ProductListingStruct::setListing()" is deprecated. Pass a "ProductListingResult" instead.'
        ));

        $struct->setListing($this->createSearchResult());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the setListing() parameter type narrowing to ProductListingResult
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSetListingStillAcceptsPlainSearchResult(): void
    {
        $searchResult = $this->createSearchResult();

        $struct = new ProductListingStruct();
        $struct->setListing($searchResult);

        static::assertSame($searchResult, $struct->getListing());
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        return new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
