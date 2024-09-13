<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class ProductPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductDetailRoute $productDetailRoute
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
            ->addAssociation('media');

        $this->eventDispatcher->dispatch(new ProductPageCriteriaEvent($productId, $criteria, $context));

        $result = $this->productDetailRoute->load($productId, $request, $context, $criteria);
        $product = $result->getProduct();

        $product->getMedia()?->sort(fn (ProductMediaEntity $a, ProductMediaEntity $b) => $a->getPosition() <=> $b->getPosition());

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

        $this->addDeprecatedData($page);

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
     * @deprecated tag:v6.7.0 - Will be removed as the data is not used anymore
     */
    private function addDeprecatedData(ProductPage $page): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            return;
        }

        $sections = $page->getCmsPage()?->getSections();
        if ($sections === null) {
            return;
        }

        $blocks = new CmsBlockCollection();
        foreach ($sections as $section) {
            $sectionBlocks = $section->getBlocks();
            if ($sectionBlocks === null) {
                continue;
            }

            $blocks->merge($sectionBlocks);
        }

        $descriptionReviewsStruct = $blocks->filterByProperty('type', ProductDescriptionReviewsCmsElementResolver::TYPE)->first()?->getSlots()?->first()?->getData();
        if ($descriptionReviewsStruct instanceof ProductDescriptionReviewsStruct) {
            $productReviewResult = $descriptionReviewsStruct->getReviews();
            if ($productReviewResult !== null) {
                $page->setReviews(ReviewLoaderResult::createFrom($productReviewResult));
            }
        }

        $crossSellingStruct = $blocks->filterByProperty('type', CrossSellingCmsElementResolver::TYPE)->first()?->getSlots()?->first()?->getData();
        if ($crossSellingStruct instanceof CrossSellingStruct) {
            $crossSelling = $crossSellingStruct->getCrossSellings();
            $page->setCrossSellings($crossSelling ?? new CrossSellingElementCollection());
        }
    }
}
