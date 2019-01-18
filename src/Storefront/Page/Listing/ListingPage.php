<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Storefront\Framework\Page\PageWithHeader;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class ListingPage extends PageWithHeader
{
    /**
     * @var StorefrontSearchResult
     */
    protected $listing;

    public function getListing(): StorefrontSearchResult
    {
        return $this->listing;
    }

    public function setListing(StorefrontSearchResult $listing): void
    {
        $this->listing = $listing;
    }
}
