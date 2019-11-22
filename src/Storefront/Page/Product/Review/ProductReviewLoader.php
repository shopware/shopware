<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductReviewLoader
{
    private const LIMIT = 10;
    private const DEFAULT_PAGE = 1;
    private const ACTIVE_STATUS = 1;
    private const ALL_LANGUAGES = 'all';

    /**
     * @var EntityRepositoryInterface
     */
    private $reviewRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $reviewRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->reviewRepository = $reviewRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * load reviews for one product. The request must contain the productId
     * otherwise MissingRequestParameterException is thrown
     *
     * @throws MissingRequestParameterException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): ReviewLoaderResult
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId');
        }

        $criteria = $this->createReviewCriteria($request);

        $reviews = $this->getReviews($criteria, $context, $request);

        $this->eventDispatcher->dispatch(new ProductReviewsLoadedEvent($reviews, $context, $request));

        $reviewResult = ReviewLoaderResult::createFrom($reviews);
        $reviewResult->setProductId($productId);
        $aggregation = $reviews->getAggregations()->get('ratingMatrix');
        $matrix = new RatingMatrix([]);

        if ($aggregation instanceof TermsResult) {
            $matrix = new RatingMatrix($aggregation->getBuckets());
        }
        $reviewResult->setMatrix($matrix);
        $reviewResult->setCustomerReview($this->loadProductCustomerReview($request, $context));
        $reviewResult->setTotalReviews($this->getUnfilteredReviewCount($request, $context));

        return $reviewResult;
    }

    /**
     * load product review for a specific user
     * if there is no customer or customer has not written a review
     * null is returned otherwise review entity is returned
     *
     * @throws MissingRequestParameterException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function loadProductCustomerReview(Request $request, SalesChannelContext $context): ?ProductReviewEntity
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId');
        }

        return $this->getCustomerReview($productId, $context);
    }

    /**
     * get reviews with the users language
     * if there aren't any reviews then get them with any language
     */
    private function getReviews(Criteria $criteria, SalesChannelContext $context, Request $request): StorefrontSearchResult
    {
        if ($request->get('language') !== self::ALL_LANGUAGES) {
            $languageCriteria = clone $criteria;
            $languageCriteria->addFilter(new EqualsFilter('languageId', $this->getLanguageId($context)));
            $reviews = $this->reviewRepository->search($languageCriteria, $context->getContext());

            if ($reviews->count() > 0) {
                return StorefrontSearchResult::createFrom($reviews);
            }
        }

        $reviews = $this->reviewRepository->search($criteria, $context->getContext());

        return StorefrontSearchResult::createFrom($reviews);
    }

    /**
     * @throws MissingRequestParameterException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function createReviewCriteria(Request $request): Criteria
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId');
        }

        $limit = (int) $request->get('limit', self::LIMIT);
        $page = (int) $request->get('p', self::DEFAULT_PAGE);
        $offset = $limit * ($page - 1);

        $criteria = (new Criteria())
            ->setLimit($limit)
            ->setOffset($offset)
            ->addFilter(
                new EqualsFilter('status', self::ACTIVE_STATUS),
                new EqualsFilter('productId', $productId)
            );
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');

        if ($request->get('sort', 'points') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        $points = $request->get('points', []);

        if (is_array($points) && count($points) > 0) {
            $pointFilter = [];
            foreach ($points as $point) {
                $pointFilter[] = new EqualsFilter('points', $point);
            }

            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $pointFilter));
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'status-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [new EqualsFilter('status', 1)]
            )
        );

        return $criteria;
    }

    /**
     * get language id by customer
     *if customer does not exist get language id from context
     */
    private function getLanguageId(SalesChannelContext $context): string
    {
        if ($context->getCustomer()) {
            return $context->getCustomer()->getLanguageId();
        }

        return $context->getContext()->getLanguageId();
    }

    /**
     * get review by productId and customer
     * a customer should only create one review per product, so if there are more than one
     * review we only take one
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getCustomerReview(string $productId, SalesChannelContext $context): ?ProductReviewEntity
    {
        $customer = $context->getCustomer();
        if (!$customer) {
            return null;
        }

        $criteria = $this->createCustomerReviewCriteria($productId, $customer->getId());

        $customerReviews = $this->reviewRepository->search($criteria, $context->getContext());

        if ($customerReviews->count() > 0) {
            return $customerReviews->first();
        }

        return null;
    }

    /**
     * get criteria for customer product review
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function createCustomerReviewCriteria(string $productId, string $customerId): Criteria
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->setOffset(0)
            ->addFilter(
                new EqualsFilter('productId', $productId),
                new EqualsFilter('customerId', $customerId)
            );

        return $criteria;
    }

    private function getUnfilteredReviewCount(Request $request, SalesChannelContext $context): int
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId');
        }

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(
                new EqualsFilter('status', self::ACTIVE_STATUS),
                new EqualsFilter('productId', $productId)
            );
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $customerReviews = $this->reviewRepository->search($criteria, $context->getContext());

        return $customerReviews->getTotal();
    }
}
