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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Subscriber\SearchTermSubscriber;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route(service="Shopware\Storefront\Controller\Widgets\ListingController", path="/")
 */
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
     * @Route("/widgets/listing/top_seller", name="widgets_top_seller")
     * @Method({"GET"})
     */
    public function topSellerAction(StorefrontContext $context)
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $products = $this->repository->search($criteria, $context);

        return $this->renderStorefront('@Storefront/widgets/listing/top_seller.html.twig', [
            'products' => $products,
        ]);
    }

    /**
     * @Route("/widgets/listing/list", name="widgets_listing_list")
     * @Method({"GET"})
     */
    public function listAction(Request $request, StorefrontContext $context)
    {
        $categoryId = $request->query->get('categoryId');

        if ($request->query->has(SearchTermSubscriber::TERM_PARAMETER)) {
            $page = $this->searchPageLoader->load($request, $context, false);
        } elseif ($request->query->has('categoryId')) {
            $page = $this->listingPageLoader->load($categoryId, $request, $context, false);
        } else {
            throw new \RuntimeException('Requires category id or search term');
        }

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
            'listing' => $page,
        ], null, false);

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
            'listing' => $page,
        ], null, false);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getProducts()->getTotal(),
        ]);
    }
}
