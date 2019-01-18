<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class SuggestPagelet extends Struct
{
    /**
     * @var StorefrontSearchResult
     */
    protected $listing;

    /**
     * @var string
     */
    protected $searchTerm;

    public function __construct(StorefrontSearchResult $listing, string $searchTerm)
    {
        $this->listing = $listing;
        $this->searchTerm = $searchTerm;
    }

    public function getListing(): StorefrontSearchResult
    {
        return $this->listing;
    }

    public function setListing(StorefrontSearchResult $listing): void
    {
        $this->listing = $listing;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }
}
