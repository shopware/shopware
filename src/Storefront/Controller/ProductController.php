<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Product\ProductPageLoadedHook;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class ProductController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductPageLoader $productPageLoader,
        private readonly AbstractFindProductVariantRoute $findVariantRoute,
        private readonly MinimalQuickViewPageLoader $minimalQuickViewPageLoader,
        private readonly AbstractProductReviewSaveRoute $productReviewSaveRoute,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly ProductReviewLoader $productReviewLoader,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    #[Route(path: '/detail/{productId}', name: 'frontend.detail.page', defaults: ['_httpCache' => true], methods: ['GET'])]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        $this->hook(new ProductPageLoadedHook($page, $context));

        $ratingSuccess = $request->get('success');

        /**
         * @deprecated tag:v6.6.0 - remove complete if statement, cms page id is always set
         *
         * Fallback layout for non-assigned product layout
         */
        if (!$page->getCmsPage()) {
            Feature::throwException('v6.6.0.0', 'Fallback will be removed because cms page is always set in subscriber.');

            return $this->renderStorefront('@Storefront/storefront/page/product-detail/index.html.twig', ['page' => $page, 'ratingSuccess' => $ratingSuccess]);
        }

        return $this->renderStorefront('@Storefront/storefront/page/content/product-detail.html.twig', ['page' => $page]);
    }

    #[Route(path: '/detail/{productId}/switch', name: 'frontend.detail.switch', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET'])]
    public function switch(string $productId, Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $switchedGroup = $request->query->has('switched') ? (string) $request->query->get('switched') : null;

        /** @var array<mixed>|null $options */
        $options = json_decode($request->query->get('options', ''), true);

        try {
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
        } catch (VariantNotFoundException|ProductNotFoundException) {
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

        return new JsonResponse([
            'url' => $url,
            'productId' => $productId,
        ]);
    }

    #[Route(path: '/quickview/{productId}', name: 'widgets.quickview.minimal', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function quickviewMinimal(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        $this->hook(new ProductQuickViewWidgetLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
    }

    #[Route(path: '/product/{productId}/rating', name: 'frontend.detail.review.save', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
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

    #[Route(path: '/product/{productId}/reviews', name: 'frontend.product.reviews', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
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
