<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductDescriptionReviewsCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    private const LIMIT = 10;
    private const DEFAULT_PAGE = 1;
    private const FILTER_LANGUAGE = 'filter-language';

    /**
     * @internal
     */
    public function __construct(private readonly AbstractProductReviewRoute $productReviewRoute)
    {
    }

    public function getType(): string
    {
        return 'product-description-reviews';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductDescriptionReviewsStruct();
        $slot->setData($data);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null) {
            return;
        }

        $request = $resolverContext->getRequest();
        $ratingSuccess = (bool) $request->get('success', false);
        $data->setRatingSuccess($ratingSuccess);

        $product = null;

        if ($productConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());
        }

        if ($productConfig->isStatic()) {
            $product = $this->getSlotProduct($slot, $result, $productConfig->getStringValue());
        }

        /** @var SalesChannelProductEntity|null $product */
        if ($product !== null) {
            $data->setProduct($product);
            $data->setReviews($this->loadProductReviews($product, $request, $resolverContext->getSalesChannelContext()));
        }
    }

    private function loadProductReviews(SalesChannelProductEntity $product, Request $request, SalesChannelContext $context): ProductReviewResult
    {
        $reviewCriteria = $this->createReviewCriteria($request, $context);
        $reviews = $this->productReviewRoute
            ->load($product->getParentId() ?? $product->getId(), $request, $context, $reviewCriteria)
            ->getResult();

        $matrix = $this->getReviewRatingMatrix($reviews);

        $reviewResult = ProductReviewResult::createFrom($reviews);
        $reviewResult->setMatrix($matrix);
        $reviewResult->setProductId($product->getId());
        $reviewResult->setCustomerReview($this->getCustomerReview($product->getId(), $context));
        $reviewResult->setTotalReviews($matrix->getTotalReviewCount());
        $reviewResult->setProductId($product->getId());
        $reviewResult->setParentId($product->getParentId() ?? $product->getId());

        return $reviewResult;
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
