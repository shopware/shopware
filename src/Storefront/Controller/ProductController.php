<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\SalesChannel\ProductReviewService;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @var ProductReviewService
     */
    private $productReviewService;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    /**
     * @var ProductReviewLoader
     */
    private $reviewPageletLoader;

    public function __construct(
        ProductPageLoader $productPageLoader,
        ProductCombinationFinder $combinationFinder,
        MinimalQuickViewPageLoader $minimalQuickViewPageLoader,
        ProductReviewService $productReviewService,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        ProductReviewLoader $reviewPageletLoader
    ) {
        $this->productPageLoader = $productPageLoader;
        $this->combinationFinder = $combinationFinder;
        $this->minimalQuickViewPageLoader = $minimalQuickViewPageLoader;
        $this->productReviewService = $productReviewService;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
        $this->reviewPageletLoader = $reviewPageletLoader;
    }

    /**
     * @HttpCache()
     * @Route("/detail/{productId}", name="frontend.detail.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        $ratingSuccess = $request->get('success');

        return $this->renderStorefront('@Storefront/storefront/page/product-detail/index.html.twig', ['page' => $page, 'ratingSuccess' => $ratingSuccess]);
    }

    /**
     * @HttpCache()
     * @Route("/detail/{productId}/switch", name="frontend.detail.switch", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function switch(string $productId, Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $switchedOption = $request->query->get('switched');

        $newOptions = json_decode($request->query->get('options'), true);

        $redirect = $this->combinationFinder->find($productId, $switchedOption, $newOptions, $salesChannelContext);

        $host = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);

        $url = $this->seoUrlPlaceholderHandler->replace(
            $this->seoUrlPlaceholderHandler->generate(
                'frontend.detail.page',
                ['productId' => $redirect->getVariantId()]
            ),
            $host,
            $salesChannelContext
        );

        return new JsonResponse(['url' => $url]);
    }

    /**
     * @Route("/quickview/{productId}", name="widgets.quickview.minimal", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function quickviewMinimal(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/product/{productId}/rating", name="frontend.detail.review.save", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function saveReview(string $productId, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        try {
            $this->productReviewService->save($productId, $data, $context);
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
     * @Route("/product/{productId}/reviews", name="frontend.product.reviews", methods={"GET","POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function loadReviews(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $reviews = $this->reviewPageletLoader->load($request, $context);

        return $this->renderStorefront('storefront/page/product-detail/review/review.html.twig', [
            'reviews' => $reviews,
            'ratingSuccess' => $request->get('success'),
        ]);
    }
}
