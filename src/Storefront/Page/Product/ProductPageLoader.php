<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductDetailRoute $productDetailRoute,
        private readonly AbstractProductReviewRoute $productReviewRoute,
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
        $this->loadReviewData($page, $context);

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
     * Loads a representative sample of approved reviews and the total approved review
     * count for use in the JSON-LD Product schema (AggregateRating + Review items).
     * The ratingMatrix aggregation counts all approved reviews across every page in a
     * single query, so ratingCount is always the honest total regardless of MAX_REVIEWS_IN_JSON_LD.
     */
    private function loadReviewData(ProductPage $page, SalesChannelContext $context): void
    {
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
        $criteria->addFilter(new EqualsFilter('status', true));

        $criteria->addAggregation(
            new FilterAggregation(
                'json-ld-status-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [new EqualsFilter('status', true)]
            )
        );

        $entityResult = $this->productReviewRoute
            ->load($productId, new Request(), $context, $criteria)
            ->getResult();

        $aggregation = $entityResult->getAggregations()->get('ratingMatrix');
        $matrix = new RatingMatrix($aggregation instanceof TermsResult ? $aggregation->getBuckets() : []);

        $reviewResult = ProductReviewResult::createFrom($entityResult);
        $reviewResult->setMatrix($matrix);
        $reviewResult->setProductId($productId);
        $reviewResult->setTotalReviewsInCurrentLanguage($entityResult->getTotal());

        $page->setReviewData($reviewResult);
    }
}
