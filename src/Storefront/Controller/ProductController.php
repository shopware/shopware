<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ProductController extends StorefrontController
{
    /**
     * @var ProductPageLoader
     */
    private $productPageLoader;

    /**
     * @var ProductCombinationFinder
     */
    private $combinationFinder;

    /**
     * @var MinimalQuickViewPageLoader
     */
    private $minimalQuickViewPageLoader;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    /**
     * @var ProductReviewLoader
     */
    private $productReviewLoader;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    private AbstractProductReviewSaveRoute $productReviewSaveRoute;

    public function __construct(
        ProductPageLoader $productPageLoader,
        ProductCombinationFinder $combinationFinder,
        MinimalQuickViewPageLoader $minimalQuickViewPageLoader,
        AbstractProductReviewSaveRoute $productReviewSaveRoute,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        ProductReviewLoader $productReviewLoader,
        SystemConfigService $systemConfigService
    ) {
        $this->productPageLoader = $productPageLoader;
        $this->combinationFinder = $combinationFinder;
        $this->minimalQuickViewPageLoader = $minimalQuickViewPageLoader;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
        $this->productReviewLoader = $productReviewLoader;
        $this->systemConfigService = $systemConfigService;
        $this->productReviewSaveRoute = $productReviewSaveRoute;
    }

    /**
     * @Since("6.3.3.0")
     * @HttpCache()
     * @Route("/detail/{productId}", name="frontend.detail.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        $ratingSuccess = $request->get('success');

        // Fallback layout for non-assigned product layout
        if (!$page->getCmsPage()) {
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
        $switchedOption = $request->query->has('switched') ? (string) $request->query->get('switched') : null;

        $options = (string) $request->query->get('options');
        $newOptions = $options !== '' ? json_decode($options, true) : [];

        try {
            $redirect = $this->combinationFinder->find($productId, $switchedOption, $newOptions, $salesChannelContext);

            $productId = $redirect->getVariantId();
        } catch (ProductNotFoundException $productNotFoundException) {
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

        $response = new JsonResponse(['url' => $url]);
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

        return $this->renderStorefront('@Storefront/storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route("/product/{productId}/rating", name="frontend.detail.review.save", methods={"POST"}, defaults={"XmlHttpRequest"=true})
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
