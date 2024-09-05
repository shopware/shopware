<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent;
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
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductReviewLoader extends AbstractProductReviewLoader
{
    public const FILTER_LANGUAGE = 'filter-language';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductReviewRoute $productReviewRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractProductReviewLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Request $request, SalesChannelContext $context, string $productId, ?string $parentId = null): ProductReviewResult
    {
        $reviewCriteria = $this->createReviewCriteria($request, $context);
        $reviews = $this->productReviewRoute
            ->load($parentId ?? $productId, $request, $context, $reviewCriteria)
            ->getResult();

        $reviewResult = ProductReviewResult::createFrom($reviews);
        $reviewResult->setMatrix($this->getReviewRatingMatrix($reviews));
        $reviewResult->setCustomerReview($this->getCustomerReview($productId, $context));
        $reviewResult->setTotalReviews($reviews->getTotal());
        $reviewResult->setTotalNativeReviews($this->getTotalNativeReviews($reviews));
        $reviewResult->setProductId($productId);
        $reviewResult->setParentId($parentId ?? $productId);

        $this->eventDispatcher->dispatch(new ProductReviewsLoadedEvent($reviewResult, $context, $request));

        return $reviewResult;
    }

    /**
     * @param EntitySearchResult<ProductReviewCollection> $reviews
     */
    protected function getReviewRatingMatrix(EntitySearchResult $reviews): RatingMatrix
    {
        $aggregation = $reviews->getAggregations()->get('ratingMatrix');

        if ($aggregation instanceof TermsResult) {
            return new RatingMatrix($aggregation->getBuckets());
        }

        return new RatingMatrix([]);
    }

    /**
     * @param EntitySearchResult<ProductReviewCollection> $reviews
     */
    protected function getTotalNativeReviews(EntitySearchResult $reviews): int
    {
        $aggregation = $reviews->getAggregations()->get('languageMatrix');

        if ($aggregation instanceof TermsResult) {
            $buckets = $aggregation->getBuckets();

            return empty($buckets) ? 0 : $buckets[0]->getCount();
        }

        return $reviews->getTotal();
    }

    protected function createReviewCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = (int) $request->get('limit', $this->systemConfigService->getInt('core.listing.reviewsPerPage', $context->getSalesChannelId()));
        $page = (int) $request->get('p', 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if ($request->get('sort', 'createdAt') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if ($request->get('language') === self::FILTER_LANGUAGE) {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getContext()->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $this->handlePointsAggregation($request, $criteria, $context);

        return $criteria;
    }

    protected function getCustomerReview(string $productId, SalesChannelContext $context): ?ProductReviewEntity
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
            ->getResult()->getEntities();

        return $customerReviews->first();
    }

    protected function handlePointsAggregation(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $reviewFilters = [];
        $points = $request->get('points', []);

        if (\is_array($points) && \count($points) > 0) {
            $pointFilter = [];
            foreach ($points as $point) {
                $pointFilter[] = new RangeFilter('points', [
                    'gte' => (int) $point - 0.5,
                    'lt' => (int) $point + 0.5,
                ]);
            }

            $criteria->addPostFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $pointFilter));
        }

        $reviewFilters[] = new EqualsFilter('status', true);
        if ($context->getCustomer() !== null) {
            $reviewFilters[] = new EqualsFilter('customerId', $context->getCustomer()->getId());
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'customer-login-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [
                    new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                ]
            ),
            new FilterAggregation(
                'language-filter',
                new TermsAggregation('languageMatrix', 'languageId'),
                [
                    new EqualsFilter('languageId', $context->getContext()->getLanguageId()),
                    new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                ]
            )
        );
    }
}
