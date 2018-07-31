<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Storefront\Controller\Widgets;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ListingController extends StorefrontController
{
    /**
     * @var StorefrontProductRepository
     */
    private $repository;

    /**
     * @var ListingPageLoader
     */
    private $listingPageLoader;

    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(
        StorefrontProductRepository $repository,
        ListingPageLoader $listingPageLoader,
        SearchPageLoader $searchPageLoader
    ) {
        $this->repository = $repository;
        $this->listingPageLoader = $listingPageLoader;
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/widgets/listing/top_seller", name="widgets_top_seller", methods={"GET"})
     */
    public function topSellerAction(CheckoutContext $context)
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $products = $this->repository->search($criteria, $context);

        return $this->renderStorefront('@Storefront/widgets/listing/top_seller.html.twig', [
            'products' => $products,
        ]);
    }

    /**
     * @Route("/widgets/listing/list/{categoryId}", name="widgets_listing_list", methods={"GET"})
     */
    public function listAction(string $categoryId, ListingPageRequest $request, CheckoutContext $context)
    {
        $request->setNavigationId($categoryId);

        $page = $this->listingPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
            'listing' => $page,
        ]);

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
            'listing' => $page,
        ]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getProducts()->getTotal(),
        ]);
    }

    /**
     * @Route("/widgets/listing/search", name="widgets_listing_search", methods={"GET"})
     */
    public function searchAction(SearchPageRequest $request, CheckoutContext $context)
    {
        $page = $this->searchPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
            'listing' => $page,
        ]);

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
            'listing' => $page,
        ]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getProducts()->getTotal(),
        ]);
    }
}
