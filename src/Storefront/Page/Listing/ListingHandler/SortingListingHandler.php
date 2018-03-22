<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Listing\Repository\ListingSortingRepository;
use Shopware\Api\Listing\Struct\ListingSortingBasicStruct;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class SortingListingHandler implements ListingHandler
{
    /**
     * @var ListingSortingRepository
     */
    private $repository;

    public function __construct(ListingSortingRepository $repository)
    {
        $this->repository = $repository;
    }

    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void
    {
        if (!$request->query->has('o')) {
            return;
        }

        $sort = $request->query->get('o');

        $search = new Criteria();
        $search->addFilter(new TermQuery('listing_sorting.uniqueKey', $sort));

        $sortings = $this->repository->search($search, $context->getShopContext());

        if ($sortings->count() <= 0) {
            return;
        }

        /** @var ListingSortingBasicStruct $sorting */
        $sorting = $sortings->first();
        foreach ($sorting->getPayload() as $fieldSorting) {
            $criteria->addSorting($fieldSorting);
        }
    }

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void
    {
        $search = new Criteria();
        $sortings = $this->repository->search($search, $context->getShopContext());
        $listingPage->getSortings()->fill($sortings->getElements());

//
//        $sortings = [
//            [
//                'label' => 'Cheapest price',
//                'uniqueKey' => 'cheapest-price',
//                'payload' => json_encode([new FieldSorting('product.listingPrices', FieldSorting::ASCENDING)])
//            ],
//            [
//                'label' => 'Highest price',
//                'uniqueKey' => 'highest-price',
//                'payload' => json_encode([new FieldSorting('product.listingPrices', FieldSorting::DESCENDING)])
//            ],
//            [
//                'label' => 'Product name',
//                'uniqueKey' => 'product-name',
//                'payload' => json_encode([new FieldSorting('product.name', FieldSorting::ASCENDING)])
//            ]
//        ];
//
//
//        $this->repository->create($sortings, $context->getShopContext());


    }
}