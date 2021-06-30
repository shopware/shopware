<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\AbstractCmsRoute;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\SwitchBuyBoxVariantEvent;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CmsController extends StorefrontController
{
    /**
     * @var AbstractCmsRoute
     */
    private $cmsRoute;

    /**
     * @var AbstractCategoryRoute
     */
    private $categoryRoute;

    /**
     * @var AbstractProductListingRoute
     */
    private $listingRoute;

    /**
     * @var AbstractProductDetailRoute
     */
    private $productRoute;

    /**
     * @var ProductReviewLoader
     */
    private $productReviewLoader;

    /**
     * @var ProductCombinationFinder
     */
    private $combinationFinder;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        AbstractCmsRoute $cmsRoute,
        AbstractCategoryRoute $categoryRoute,
        AbstractProductListingRoute $listingRoute,
        AbstractProductDetailRoute $productRoute,
        ProductReviewLoader $productReviewLoader,
        ProductCombinationFinder $combinationFinder,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cmsRoute = $cmsRoute;
        $this->categoryRoute = $categoryRoute;
        $this->listingRoute = $listingRoute;
        $this->productRoute = $productRoute;
        $this->productReviewLoader = $productReviewLoader;
        $this->combinationFinder = $combinationFinder;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Since("6.0.0.0")
     * Route for cms data (used in XmlHttpRequest)
     *
     * @HttpCache()
     * @Route("/widgets/cms/{id}", name="frontend.cms.page", methods={"GET", "POST"}, defaults={"id"=null, "XmlHttpRequest"=true})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     */
    public function page(?string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$id) {
            throw new MissingRequestParameterException('Parameter id missing');
        }

        $cmsPage = $this->cmsRoute->load($id, $request, $salesChannelContext)->getCmsPage();

        $response = $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $cmsPage]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.0.0.0")
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
    public function category(?string $navigationId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$navigationId) {
            throw new MissingRequestParameterException('Parameter navigationId missing');
        }

        $category = $this->categoryRoute->load($navigationId, $request, $salesChannelContext)->getCategory();

        if (!$category->getCmsPage()) {
            throw new PageNotFoundException('');
        }

        $response = $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $category->getCmsPage()]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.0.0.0")
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

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, new Criteria())
            ->getResult();

        $mapped = [];
        foreach ($listing->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.4.0.0")
     * @HttpCache()
     *
     * Route to load the cms element buy box product config which assigned to the provided product id.
     * Product id is required to load the slot config for the buy box
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/cms/buybox/{productId}/switch", name="frontend.cms.buybox.switch", methods={"GET"}, defaults={"productId"=null, "XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     * @throws ProductNotFoundException
     */
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        if (!$productId) {
            throw new MissingRequestParameterException('Parameter productId missing');
        }

        /** @var string $switchedOption */
        $switchedOption = $request->query->get('switched');

        /** @var string $elementId */
        $elementId = $request->query->get('elementId');

        $options = (string) $request->query->get('options');
        /** @var array $newOptions */
        $newOptions = \strlen($options) ? json_decode($options, true) : [];

        $redirect = $this->combinationFinder->find($productId, $switchedOption, $newOptions, $context);

        $newProductId = $redirect->getVariantId();

        $result = $this->productRoute->load($newProductId, $request, $context, new Criteria());
        $product = $result->getProduct();
        $configurator = $result->getConfigurator();

        $request->request->set('parentId', $product->getParentId());
        $request->request->set('productId', $product->getId());
        $reviews = $this->productReviewLoader->load($request, $context);
        $reviews->setParentId($product->getParentId() ?? $product->getId());

        $event = new SwitchBuyBoxVariantEvent($elementId, $product, $configurator, $request, $context);
        $this->eventDispatcher->dispatch($event);

        $response = $this->renderStorefront('@Storefront/storefront/component/buy-widget/buy-widget.html.twig', [
            'product' => $product,
            'configuratorSettings' => $configurator,
            'totalReviews' => $reviews->getTotalReviews(),
            'elementId' => $elementId,
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
