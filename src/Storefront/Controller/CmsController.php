<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
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
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ProductListingGateway
     */
    private $listingGateway;

    public function __construct(
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        SalesChannelRepositoryInterface $categoryRepository,
        ProductListingGateway $listingGateway
    ) {
        $this->cmsPageLoader = $cmsPageLoader;
        $this->categoryRepository = $categoryRepository;
        $this->listingGateway = $listingGateway;
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

        $cmsPage = $this->load($id, $request, $salesChannelContext);

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

        $categories = $this->categoryRepository->search(new Criteria([$navigationId]), $salesChannelContext);

        if (!$categories->has($navigationId)) {
            throw new CategoryNotFoundException($navigationId);
        }

        /** @var CategoryEntity $category */
        $category = $categories->get($navigationId);

        if (!$category->getCmsPageId()) {
            throw new PageNotFoundException('');
        }

        $cmsPage = $this->load($category->getCmsPageId(), $request, $salesChannelContext, $category->getSlotConfig());

        return $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $cmsPage]);
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

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws PageNotFoundException
     */
    private function load(string $id, Request $request, SalesChannelContext $context, ?array $config = null): ?CmsPageEntity
    {
        $criteria = new Criteria([$id]);

        $slots = $request->get('slots');

        if (is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        $pages = $this->cmsPageLoader->load($request, $criteria, $context, $config);

        if (!$pages->has($id)) {
            throw new PageNotFoundException($id);
        }

        return $pages->get($id);
    }
}
