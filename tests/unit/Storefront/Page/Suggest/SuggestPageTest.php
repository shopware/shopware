<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Page\Suggest\SuggestPage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SuggestPage::class)]
class SuggestPageTest extends TestCase
{
    public function testSetSearchResultAcceptsProductListingResult(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        $page = new SuggestPage();
        $page->setSearchResult($listing);

        static::assertSame($listing, $page->getSearchResult());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the runtime check in setSearchResult(); the narrowed native type enforces this
     */
    public function testSetSearchResultWithPlainSearchResultIsDeprecated(): void
    {
        $page = new SuggestPage();

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing a plain "EntitySearchResult" to "SuggestPage::setSearchResult()" is deprecated. Pass a "ProductListingResult" instead.'
        ));

        $page->setSearchResult($this->createSearchResult());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the setSearchResult() parameter type narrowing to ProductListingResult
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSetSearchResultStillAcceptsPlainSearchResult(): void
    {
        $searchResult = $this->createSearchResult();

        $page = new SuggestPage();
        $page->setSearchResult($searchResult);

        static::assertSame($searchResult, $page->getSearchResult());
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
