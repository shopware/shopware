<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductPageLoader
{
    private GenericPageLoaderInterface $genericLoader;

    private EventDispatcherInterface $eventDispatcher;

    private SalesChannelCmsPageRepository $cmsPageRepository;

    private CmsSlotsDataResolver $slotDataResolver;

    private ProductDefinition $productDefinition;

    private AbstractProductDetailRoute $productDetailRoute;

    private ProductReviewLoader $productReviewLoader;

    private AbstractProductCrossSellingRoute $crossSellingRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelCmsPageRepository $cmsPageRepository,
        CmsSlotsDataResolver $slotDataResolver,
        ProductDefinition $productDefinition,
        AbstractProductDetailRoute $productDetailRoute,
        ProductReviewLoader $productReviewLoader,
        AbstractProductCrossSellingRoute $crossSellingRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->productDefinition = $productDefinition;
        $this->productDetailRoute = $productDetailRoute;
        $this->productReviewLoader = $productReviewLoader;
        $this->crossSellingRoute = $crossSellingRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws ProductNotFoundException
     */
    public function load(Request $request, SalesChannelContext $context): ProductPage
    {
        $productId = $request->attributes->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId', '/productId');
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

        if ($product->getMedia() !== null) {
            $product->getMedia()->sort(function (ProductMediaEntity $a, ProductMediaEntity $b) {
                return $a->getPosition() <=> $b->getPosition();
            });
        }

        if ($category = $product->getSeoCategory()) {
            $request->request->set('navigationId', $category->getId());
        }

        $page = $this->genericLoader->load($request, $context);
        $page = ProductPage::createFrom($page);

        $page->setProduct($product);
        $page->setConfiguratorSettings($result->getConfigurator());
        $page->setNavigationId($product->getId());

        $this->loadCmsPage($product, $page, $request, $context);
        $this->loadOptions($page);
        $this->loadMetaData($page);

        $this->eventDispatcher->dispatch(
            new ProductPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadOptions(ProductPage $page): void
    {
        $options = new PropertyGroupOptionCollection();
        $optionIds = $page->getProduct()->getOptionIds();

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

    private function loadSlotData(
        CmsPageEntity $page,
        SalesChannelContext $salesChannelContext,
        SalesChannelProductEntity $product,
        Request $request
    ): void {
        $resolverContext = new EntityResolverContext($salesChannelContext, $request, $this->productDefinition, $product);

        foreach ($page->getSections() as $section) {
            $slots = $this->slotDataResolver->resolve($section->getBlocks()->getSlots(), $resolverContext);
            $section->getBlocks()->setSlots($slots);
        }
    }

    private function getCmsPage(string $cmsPageId, SalesChannelContext $context): ?CmsPageEntity
    {
        $pages = $this->cmsPageRepository->read([$cmsPageId], $context);

        if ($pages->count() === 0) {
            return null;
        }

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        return $page;
    }

    private function loadCmsPage(SalesChannelProductEntity $product, ProductPage $page, Request $request, SalesChannelContext $context): void
    {
        if ($cmsPage = $product->getCmsPage()) {
            $page->setCmsPage($cmsPage);

            return;
        }

        $request->request->set('parentId', $product->getParentId());
        $reviews = $this->productReviewLoader->load($request, $context);
        $reviews->setParentId($product->getParentId() ?? $product->getId());

        $page->setReviews($reviews);

        $crossSellings = $this->crossSellingRoute->load($product->getId(), new Request(), $context, new Criteria());

        $page->setCrossSellings($crossSellings->getResult());

        if ($product->getCmsPageId() === null) {
            return;
        }

        $cmsPage = $this->getCmsPage($product->getCmsPageId(), $context);

        if ($cmsPage === null) {
            return;
        }

        $this->loadSlotData($cmsPage, $context, $product, $request);
        $page->setCmsPage($cmsPage);
    }
}
