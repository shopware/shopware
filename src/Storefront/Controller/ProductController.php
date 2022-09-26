<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\ProductPageLoadedHook;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class ProductController extends StorefrontController
{
    private ProductPageLoader $productPageLoader;

    private AbstractFindProductVariantRoute $findVariantRoute;

    private MinimalQuickViewPageLoader $minimalQuickViewPageLoader;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    private ProductReviewLoader $productReviewLoader;

    private SystemConfigService $systemConfigService;

    private AbstractProductReviewSaveRoute $productReviewSaveRoute;

    /**
     * @deprecated tag:v6.5.0 - will be removed
     */
    private ProductCombinationFinder $productCombinationFinder;

    /**
     * @internal
     */
    public function __construct(
        ProductPageLoader $productPageLoader,
        ProductCombinationFinder $productCombinationFinder,
        AbstractFindProductVariantRoute $findVariantRoute,
        MinimalQuickViewPageLoader $minimalQuickViewPageLoader,
        AbstractProductReviewSaveRoute $productReviewSaveRoute,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        ProductReviewLoader $productReviewLoader,
        SystemConfigService $systemConfigService
    ) {
        $this->productPageLoader = $productPageLoader;
        $this->findVariantRoute = $findVariantRoute;
        $this->minimalQuickViewPageLoader = $minimalQuickViewPageLoader;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
        $this->productReviewLoader = $productReviewLoader;
        $this->systemConfigService = $systemConfigService;
        $this->productReviewSaveRoute = $productReviewSaveRoute;
        $this->productCombinationFinder = $productCombinationFinder;
    }

    /**
     * @Since("6.3.3.0")
     * @HttpCache()
     * @Route("/detail/{productId}", name="frontend.detail.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        $this->hook(new ProductPageLoadedHook($page, $context));

        $ratingSuccess = $request->get('success');

        /**
         * @deprecated tag:v6.5.0 - remove complete if statement, cms page id is always set
         *
         * Fallback layout for non-assigned product layout
         */
        if (!$page->getCmsPage()) {
            Feature::throwException('v6.5.0.0', 'Fallback will be removed because cms page is always set in subscriber.');

            return $this->renderStorefront('@Storefront/storefront/page/product-detail/index.html.twig', ['page' => $page, 'ratingSuccess' => $ratingSuccess]);
        }

        return $this->renderStorefront('@Storefront/storefront/page/content/product-detail.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @HttpCache()
     * @Route("/detail/{productId}/switch", name="frontend.detail.switch", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function switch(string $productId, Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $switchedGroup = $request->query->has('switched') ? (string) $request->query->get('switched') : null;

        /** @var array<mixed>|null $options */
        $options = json_decode($request->query->get('options', ''), true);

        try {
            if (Feature::isActive('v6.5.0.0')) {
                $variantResponse = $this->findVariantRoute->load(
                    $productId,
                    new Request(
                        [
                            'switchedGroup' => $switchedGroup,
                            'options' => $options ?? [],
                        ]
                    ),
                    $salesChannelContext
                );

                $productId = $variantResponse->getFoundCombination()->getVariantId();
            } else {
                $finderResponse = $this->productCombinationFinder->find(
                    $productId,
                    $switchedGroup,
                    $options ?? [],
                    $salesChannelContext
                );

                $productId = $finderResponse->getVariantId();
            }
        } catch (VariantNotFoundException|ProductNotFoundException $productNotFoundException) {
            //nth
        }

        $host = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);

        $url = $this->seoUrlPlaceholderHandler->replace(
            $this->seoUrlPlaceholderHandler->generate(
                'frontend.detail.page',
                ['productId' => $productId]
            ),
            $host,
            $salesChannelContext
        );

        $response = new JsonResponse([
            'url' => $url,
            'productId' => $productId,
        ]);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');

        return $response;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/quickview/{productId}", name="widgets.quickview.minimal", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function quickviewMinimal(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        $this->hook(new ProductQuickViewWidgetLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/product/{productId}/rating", name="frontend.detail.review.save", methods={"POST"}, defaults={"XmlHttpRequest"=true, "_loginRequired"=true})
     */
    public function saveReview(string $productId, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->checkReviewsActive($context);

        try {
            $this->productReviewSaveRoute->save($productId, $data, $context);
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.product.reviews', [
                'productId' => $productId,
                'success' => -1,
                'formViolations' => $formViolations,
                'data' => $data,
            ], ['productId' => $productId]);
        }

        $forwardParams = [
            'productId' => $productId,
            'success' => 1,
            'data' => $data,
            'parentId' => $data->get('parentId'),
        ];

        if ($data->has('id')) {
            $forwardParams['success'] = 2;
        }

        return $this->forwardToRoute('frontend.product.reviews', $forwardParams, ['productId' => $productId]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/product/{productId}/reviews", name="frontend.product.reviews", methods={"GET","POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function loadReviews(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->checkReviewsActive($context);

        $reviews = $this->productReviewLoader->load($request, $context);

        $this->hook(new ProductReviewsWidgetLoadedHook($reviews, $context));

        return $this->renderStorefront('storefront/page/product-detail/review/review.html.twig', [
            'reviews' => $reviews,
            'ratingSuccess' => $request->get('success'),
        ]);
    }

    /**
     * @throws ReviewNotActiveExeption
     */
    private function checkReviewsActive(SalesChannelContext $context): void
    {
        $showReview = $this->systemConfigService->get('core.listing.showReview', $context->getSalesChannel()->getId());

        if (!$showReview) {
            throw new ReviewNotActiveExeption();
        }
    }
}
