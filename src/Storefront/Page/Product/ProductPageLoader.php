<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('framework')]
class ProductPageLoader
{
    /**
     * Maximum number of individual Review items to include in the JSON-LD output.
     *
     * Reviews are sorted by date descending (most recent first) so the sample is
     * chronologically neutral. Do NOT change the sort to points/rating — cherry-picking
     * high-rated reviews while the ratingCount reflects a lower average violates
     * review markup spam policy.
     */
    private const MAX_REVIEWS_IN_JSON_LD = 10;

    /**
     * @internal
     *
     * @param EntityRepository<ProductReviewCollection> $productReviewRepository
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductDetailRoute $productDetailRoute,
        private readonly EntityRepository $productReviewRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws ProductNotFoundException
     */
    public function load(Request $request, SalesChannelContext $context): ProductPage
    {
        $productId = $request->attributes->get('productId');
        if (!$productId) {
            throw RoutingException::missingRequestParameter('productId', '/productId');
        }

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category')
            ->addAssociation('media.media');

        $criteria->getAssociation('media')->addSorting(
            new FieldSorting('position')
        );

        $this->eventDispatcher->dispatch(new ProductPageCriteriaEvent($productId, $criteria, $context));

        $result = $this->productDetailRoute->load($productId, $request, $context, $criteria);
        $product = $result->getProduct();

        if ($product->getMedia() && $product->getCover()) {
            $product->setMedia(new ProductMediaCollection(array_merge(
                [$product->getCover()->getId() => $product->getCover()],
                $product->getMedia()->getElements()
            )));
        }

        if ($category = $product->getSeoCategory()) {
            $request->request->set('navigationId', $category->getId());
        }

        $page = $this->genericLoader->load($request, $context);
        $page = ProductPage::createFrom($page);

        $page->setProduct($product);
        $page->setConfiguratorSettings($result->getConfigurator() ?? new PropertyGroupCollection());
        $page->setNavigationId($product->getId());

        if ($cmsPage = $product->getCmsPage()) {
            $page->setCmsPage($cmsPage);
        }

        $this->loadOptions($page);
        $this->loadMetaData($page);

        if (Feature::isActive('JSON_LD_DATA')) {
            $this->loadStructuredDataReviews($page, $context);
        }

        $this->eventDispatcher->dispatch(
            new ProductPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadOptions(ProductPage $page): void
    {
        $options = new PropertyGroupOptionCollection();

        if (($optionIds = $page->getProduct()->getOptionIds()) === null) {
            $page->setSelectedOptions($options);

            return;
        }

        foreach ($page->getConfiguratorSettings() as $group) {
            $groupOptions = $group->getOptions();
            if ($groupOptions === null) {
                continue;
            }
            foreach ($optionIds as $optionId) {
                $groupOption = $groupOptions->get($optionId);
                if ($groupOption !== null) {
                    $options->add($groupOption);
                }
            }
        }

        $page->setSelectedOptions($options);
    }

    private function loadMetaData(ProductPage $page): void
    {
        $metaInformation = $page->getMetaInformation();

        if (!$metaInformation) {
            return;
        }

        $metaDescription = $page->getProduct()->getTranslation('metaDescription')
            ?? $page->getProduct()->getTranslation('description');
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaInformation->setMetaKeywords((string) $page->getProduct()->getTranslation('keywords'));

        if ((string) $page->getProduct()->getTranslation('metaTitle') !== '') {
            $metaInformation->setMetaTitle((string) $page->getProduct()->getTranslation('metaTitle'));

            return;
        }

        $metaTitleParts = [$page->getProduct()->getTranslation('name')];

        foreach ($page->getSelectedOptions() as $option) {
            $metaTitleParts[] = $option->getTranslation('name');
        }

        $metaTitleParts[] = $page->getProduct()->getProductNumber();

        $metaInformation->setMetaTitle(implode(' | ', $metaTitleParts));
    }

    /**
     * Loads a small sample of approved reviews and the total approved review count directly
     * from the repository for use in the JSON-LD Product schema (AggregateRating + Review items).
     *
     * The result is stored on ProductPage::$structuredDataReviews. It intentionally contains
     * at most MAX_REVIEWS_IN_JSON_LD items and must NOT be used to render a review list.
     */
    private function loadStructuredDataReviews(ProductPage $page, SalesChannelContext $context): void
    {
        // Don't add reviews to the structured data if reviews are disabled.
        if (!$this->systemConfigService->getBool('core.listing.showReview', $context->getSalesChannelId())) {
            return;
        }

        $product = $page->getProduct();
        $productId = $product->getParentId() ?? $product->getId();

        $criteria = new Criteria();
        $criteria->setLimit(self::MAX_REVIEWS_IN_JSON_LD);
        $criteria->setOffset(0);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        // Most recent first — neutral, unbiased sample (see MAX_REVIEWS_IN_JSON_LD comment).
        // Ratings are not allowed to be sorted by points/rating.
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addAssociation('language.translationCode');

        // Only approved reviews; also scope to this product (or its parent for variants).
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('status', true),
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $productId),
                new EqualsFilter('product.parentId', $productId),
            ]),
        ]));

        $criteria->addAggregation(
            new FilterAggregation(
                'json-ld-status-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [new EqualsFilter('status', true)]
            )
        );

        $entityResult = $this->productReviewRepository->search($criteria, $context->getContext());

        $aggregation = $entityResult->getAggregations()->get('ratingMatrix');
        $matrix = new RatingMatrix($aggregation instanceof TermsResult ? $aggregation->getBuckets() : []);

        $reviewResult = ProductReviewResult::createFrom($entityResult);
        $reviewResult->setMatrix($matrix);
        $reviewResult->setProductId($productId);
        $reviewResult->setTotalReviewsInCurrentLanguage($entityResult->getTotal());

        $page->setStructuredDataReviews($reviewResult);
    }
}
