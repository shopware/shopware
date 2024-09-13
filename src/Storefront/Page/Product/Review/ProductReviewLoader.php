<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class ProductReviewLoader
{
    private const LIMIT = 10;
    private const DEFAULT_PAGE = 1;
    private const FILTER_LANGUAGE = 'filter-language';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductReviewRoute $route,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * load reviews for one product. The request must contain the productId
     * otherwise MissingRequestParameterException is thrown
     *
     * @throws RoutingException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): ReviewLoaderResult
    {
        $productId = $request->get('parentId') ?? $request->get('productId');
        if (!$productId) {
            throw RoutingException::missingRequestParameter('productId');
        }

        $criteria = $this->createCriteria($request, $context);

        $reviews = $this->route
            ->load($productId, $request, $context, $criteria)
            ->getResult();

        if (!Feature::isActive('v6.7.0.0')) {
            $reviews = StorefrontSearchResult::createFrom($reviews);
        }

        $this->eventDispatcher->dispatch(new ProductReviewsLoadedEvent($reviews, $context, $request));

        $reviewResult = ReviewLoaderResult::createFrom($reviews);
        $reviewResult->setProductId($request->get('productId'));
        $reviewResult->setParentId($request->get('parentId'));

        $aggregation = $reviews->getAggregations()->get('ratingMatrix');
        $matrix = new RatingMatrix([]);

        if ($aggregation instanceof TermsResult) {
            $matrix = new RatingMatrix($aggregation->getBuckets());
        }
        $reviewResult->setMatrix($matrix);
        $reviewResult->setCustomerReview($this->getCustomerReview($productId, $context));
        $reviewResult->setTotalReviews($matrix->getTotalReviewCount());

        return $reviewResult;
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = (int) $request->get('limit', self::LIMIT);
        $page = (int) $request->get('p', self::DEFAULT_PAGE);
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

    /**
     * get review by productId and customer
     * a customer should only create one review per product, so if there are more than one
     * review we only take one
     *
     * @throws InconsistentCriteriaIdsException
     */
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

        $customerReviews = $this->route
            ->load($productId, new Request(), $context, $criteria)
            ->getResult()->getEntities();

        return $customerReviews->first();
    }

    private function handlePointsAggregation(Request $request, Criteria $criteria, SalesChannelContext $context): void
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
            )
        );
    }
}
