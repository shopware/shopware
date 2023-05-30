<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\AbstractCmsRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\SwitchBuyBoxVariantEvent;
use Shopware\Storefront\Page\Cms\CmsPageLoadedHook;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('content')]
class CmsController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCmsRoute $cmsRoute,
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly AbstractProductDetailRoute $productRoute,
        private readonly ProductReviewLoader $productReviewLoader,
        private readonly AbstractFindProductVariantRoute $findVariantRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(path: '/widgets/cms/{id}', name: 'frontend.cms.page', defaults: ['id' => null, 'XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET', 'POST'])]
    public function page(?string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        $page = $this->cmsRoute->load($id, $request, $salesChannelContext)->getCmsPage();

        $this->hook(new CmsPageLoadedHook($page, $salesChannelContext));

        $response = $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Navigation id is required to load the slot config for the navigation
     */
    #[Route(path: '/widgets/cms/navigation/{navigationId}', name: 'frontend.cms.navigation.page', defaults: ['navigationId' => null, 'XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function category(?string $navigationId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$navigationId) {
            throw RoutingException::missingRequestParameter('navigationId');
        }

        $category = $this->categoryRoute->load($navigationId, $request, $salesChannelContext)->getCategory();

        $page = $category->getCmsPage();
        if (!$page) {
            throw new PageNotFoundException('');
        }

        $this->hook(new CmsPageLoadedHook($page, $salesChannelContext));

        $response = $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', ['cmsPage' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the listing filters
     */
    #[Route(path: '/widgets/cms/navigation/{navigationId}/filter', name: 'frontend.cms.navigation.filter', defaults: ['XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET', 'POST'])]
    public function filter(string $navigationId, Request $request, SalesChannelContext $context): Response
    {
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

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the cms element buy box product config which assigned to the provided product id.
     * Product id is required to load the slot config for the buy box
     */
    #[Route(path: '/widgets/cms/buybox/{productId}/switch', name: 'frontend.cms.buybox.switch', defaults: ['productId' => null, 'XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET'])]
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        /** @var string $elementId */
        $elementId = $request->query->get('elementId');

        /** @var string[]|null $options */
        $options = json_decode($request->query->get('options', ''), true);

        $variantResponse = $this->findVariantRoute->load(
            $productId,
            new Request(
                [
                    'switchedGroup' => $request->query->get('switched'),
                    'options' => $options ?? [],
                ]
            ),
            $context
        );

        $newProductId = $variantResponse->getFoundCombination()->getVariantId();

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
