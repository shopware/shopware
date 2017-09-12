<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Category\Repository\CategoryRepository;
use Shopware\Context\Struct\TranslationContext;

class ListingPageLoader
{
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function load(string $categoryUuid, TranslationContext $context): ListingPageStruct
    {
        $listingPageStruct = new ListingPageStruct();

        $categories = $this->categoryRepository->read([$categoryUuid], $context);

        $listingPageStruct->setCategory($categories->get($categoryUuid));

        return $listingPageStruct;
    }
}