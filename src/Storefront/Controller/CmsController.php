<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\AbstractCmsRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CmsController extends StorefrontController
{
    /**
     * @var ProductListingGateway
     */
    private $listingGateway;

    /**
     * @var AbstractCmsRoute
     */
    private $cmsRoute;

    /**
     * @var AbstractCategoryRoute
     */
    private $categoryRoute;

    public function __construct(
        ProductListingGateway $listingGateway,
        AbstractCmsRoute $cmsRoute,
        AbstractCategoryRoute $categoryRoute
    ) {
        $this->listingGateway = $listingGateway;
        $this->cmsRoute = $cmsRoute;
        $this->categoryRoute = $categoryRoute;
    }

    /**
     * Route for cms data (used in XmlHttpRequest)
     *
     * @HttpCache()
     * @Route("/widgets/cms/{id}", name="frontend.cms.page", methods={"GET", "POST"}, defaults={"id"=null, "XmlHttpRequest"=true})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     */
    public function page(string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$id) {
            throw new MissingRequestParameterException('Parameter id missing');
        }

        $cmsPage = $this->cmsRoute->load($id, $request, $salesChannelContext)->getCmsPage();

        return $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $cmsPage]);
    }

    /**
     * Route to load a cms page which assigned to the provided navigation id.
     * Navigation id is required to load the slot config for the navigation
     *
     * @Route("/widgets/cms/navigation/{navigationId}", name="frontend.cms.navigation.page", methods={"GET", "POST"}, defaults={"navigationId"=null, "XmlHttpRequest"=true})
     *
     * @throws CategoryNotFoundException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function category(string $navigationId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$navigationId) {
            throw new MissingRequestParameterException('Parameter navigationId missing');
        }

        $category = $this->categoryRoute->load($navigationId, $request, $salesChannelContext)->getCategory();

        if (!$category->getCmsPageId()) {
            throw new PageNotFoundException('');
        }

        return $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $category->getCmsPage()]);
    }

    /**
     * @HttpCache()
     *
     * Route to load the listing filters
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/cms/navigation/{navigationId}/filter", name="frontend.cms.navigation.filter", methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function filter(string $navigationId, Request $request, SalesChannelContext $context): Response
    {
        if (!$navigationId) {
            throw new MissingRequestParameterException('Parameter navigationId missing');
        }

        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);

        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);

        $listing = $this->listingGateway->search($request, $context);

        $mapped = [];
        foreach ($listing->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        return new JsonResponse($mapped);
    }
}
