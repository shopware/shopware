<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\SalesChannel\ProductReviewService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
     * @var ProductReviewLoader
     */
    private $productReviewLoader;

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
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        ProductPageLoader $productPageLoader,
        ProductCombinationFinder $combinationFinder,
        MinimalQuickViewPageLoader $minimalQuickViewPageLoader,
        ProductReviewLoader $productReviewLoader,
        ProductReviewService $productReviewService,
        SalesChannelRepositoryInterface $productRepository
    ) {
        $this->productPageLoader = $productPageLoader;
        $this->productReviewLoader = $productReviewLoader;
        $this->combinationFinder = $combinationFinder;
        $this->minimalQuickViewPageLoader = $minimalQuickViewPageLoader;
        $this->productReviewService = $productReviewService;
        $this->productRepository = $productRepository;
    }

    /**
     * @Route("/detail/{productId}", name="frontend.detail.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        $ratingSuccess = $request->get('success');

        return $this->renderStorefront('@Storefront/page/product-detail/index.html.twig', ['page' => $page, 'ratingSuccess' => $ratingSuccess]);
    }

    /**
     * @Route("/detail/{productId}/switch", name="frontend.detail.switch", methods={"POST"})
     */
    public function switch(string $productId, RequestDataBag $data, SalesChannelContext $context): RedirectResponse
    {
        $switchedOption = $data->get('switched');
        $newOptions = json_decode($data->get('options'), true);

        $redirect = $this->combinationFinder->find(
            $productId,
            $switchedOption,
            $newOptions,
            $context
        );

        $product = $this->productRepository->search(
            new Criteria([$redirect->getVariantId()]),
            $context
        )->get($redirect->getVariantId());

        if (!$product->hasExtension('canonicalUrl')) {
            return $this->redirectToRoute('frontend.detail.page', ['productId' => $redirect->getVariantId()]);
        }

        /** @var SeoUrlEntity $canonical */
        $canonical = $product->getExtension('canonicalUrl');

        return $this->redirect($canonical->getUrl());
    }

    /**
     * @Route("/quickview/{productId}", name="widgets.quickview.minimal", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function quickviewMinimal(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
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
            return $this->forward('Shopware\Storefront\Controller\ProductController::loadReviews', [
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
        ];

        if ($data->has('id')) {
            $forwardParams['success'] = 2;
        }

        return $this->forward('Shopware\Storefront\Controller\ProductController::loadReviews', $forwardParams);
    }

    /**
     * @Route("/product/{productId}/reviews", name="frontend.product.reviews", methods={"GET","POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function loadReviews(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        return $this->renderStorefront('page/product-detail/review/review.html.twig', [
            'page' => $page,
            'ratingSuccess' => $request->get('success'),
        ]);
    }
}
