<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal (flag:FEATURE_NEXT_10078)
 */
class ProductDescriptionReviewsCmsElementResolver extends AbstractCmsElementResolver
{
    private const LIMIT = 10;
    private const DEFAULT_PAGE = 1;
    private const FILTER_LANGUAGE = 'filter-language';
    private const PRODUCT_DETAIL_ROUTE = 'frontend.detail.page';

    /**
     * @var AbstractProductDetailRoute
     */
    private $productRoute;

    /**
     * @var AbstractProductReviewRoute;
     */
    private $productReviewRoute;

    public function __construct(
        AbstractProductDetailRoute $productRoute,
        AbstractProductReviewRoute $productReviewRoute
    ) {
        $this->productRoute = $productRoute;
        $this->productReviewRoute = $productReviewRoute;
    }

    public function getType(): string
    {
        return 'product-description-reviews';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductDescriptionReviewsStruct();
        $slot->setData($data);

        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if ($productConfig && $resolverContext instanceof EntityResolverContext && $resolverContext->getRequest()->get('_route') === self::PRODUCT_DETAIL_ROUTE) {
            $productId = $resolverContext->getRequest()->get('productId');
            $productConfig->assign([
                'value' => $productId,
            ]);
        }

        if (!$productConfig || $productConfig->getValue() === null) {
            return;
        }

        $this->resolveProductFromRemote($data, $resolverContext, $productConfig->getValue());
    }

    private function resolveProductFromRemote(ProductDescriptionReviewsStruct $struct, ResolverContext $resolverContext, string $productId): void
    {
        $context = $resolverContext->getSalesChannelContext();

        $criteria = (new Criteria())
            ->addAssociation('options.group')
            ->addAssociation('properties.group');

        $result = $this->productRoute->load($productId, new Request(), $context, $criteria);

        /** @var SalesChannelProductEntity|null $product */
        $product = $result->getProduct();

        if (!$product) {
            return;
        }

        $struct->setProduct($product);
        $struct->setProductId($product->getId());

        $request = $resolverContext->getRequest();
        $ratingSuccess = (bool) $request->get('success', false);
        $struct->setRatingSuccess($ratingSuccess);

        $reviewCriteria = $this->createReviewCriteria($request, $context);
        $reviews = $this->productReviewRoute
            ->load($productId, $request, $context, $reviewCriteria)
            ->getResult();

        $matrix = $this->getReviewRatingMatrix($reviews);

        $reviewResult = ProductReviewResult::createFrom($reviews);
        $reviewResult->setMatrix($matrix);
        $reviewResult->setProductId($productId);
        $reviewResult->setCustomerReview($this->getCustomerReview($productId, $context));
        $reviewResult->setTotalReviews($matrix->getTotalReviewCount());
        $reviewResult->setProductId($product->getId());
        $reviewResult->setParentId($product->getParentId() ?? $product->getId());

        $struct->setReviews($reviewResult);
    }

    private function createReviewCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = (int) $request->get('limit', self::LIMIT);
        $page = (int) $request->get('p', self::DEFAULT_PAGE);
        $offset = $limit * ($page - 1);

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if ($request->get('sort', 'points') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if ($request->get('language') === self::FILTER_LANGUAGE) {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getContext()->getLanguageId())
            );
        }

        $this->handlePointsAggregation($request, $criteria);

        return $criteria;
    }

    private function handlePointsAggregation(Request $request, Criteria $criteria): void
    {
        $points = $request->get('points', []);

        if (\is_array($points) && \count($points) > 0) {
            $pointFilter = [];
            foreach ($points as $point) {
                $pointFilter[] = new RangeFilter('points', [
                    'gte' => $point - 0.5,
                    'lt' => $point + 0.5,
                ]);
            }

            $criteria->addPostFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $pointFilter));
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'status-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [new EqualsFilter('status', 1)]
            )
        );
    }

    private function getCustomerReview(string $productId, SalesChannelContext $context): ?ProductReviewEntity
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setOffset(0);
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $customerReviews = $this->productReviewRoute
            ->load($productId, new Request(), $context, $criteria)
            ->getResult();

        return $customerReviews->first();
    }

    private function getReviewRatingMatrix(EntitySearchResult $reviews): RatingMatrix
    {
        $aggregation = $reviews->getAggregations()->get('ratingMatrix');

        if ($aggregation instanceof TermsResult) {
            return new RatingMatrix($aggregation->getBuckets());
        }

        return new RatingMatrix([]);
    }
}
