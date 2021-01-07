<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelCmsPageRepository
     */
    private $cmsPageRepository;

    /**
     * @var CmsSlotsDataResolver
     */
    private $slotDataResolver;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var ProductLoader
     */
    private $productLoader;

    /**
     * @var ProductReviewLoader
     */
    private $productReviewLoader;

    /**
     * @var CrossSellingLoader
     */
    private $crossSellingLoader;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelCmsPageRepository $cmsPageRepository,
        CmsSlotsDataResolver $slotDataResolver,
        ProductDefinition $productDefinition,
        ProductLoader $productLoader,
        ProductReviewLoader $productReviewLoader,
        CrossSellingLoader $crossSellingLoader
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->productDefinition = $productDefinition;
        $this->productLoader = $productLoader;
        $this->productReviewLoader = $productReviewLoader;
        $this->crossSellingLoader = $crossSellingLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws ProductNotFoundException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): ProductPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);
        $page = ProductPage::createFrom($page);

        $productId = $request->attributes->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId', '/productId');
        }

        $product = $this->productLoader->load($productId, $salesChannelContext, ProductPageCriteriaEvent::class);
        $page->setProduct($product);
        $page->setConfiguratorSettings($product->getConfigurator());

        $request->request->set('parentId', $product->getParentId());
        $reviews = $this->productReviewLoader->load($request, $salesChannelContext);
        $reviews->setParentId($product->getParentId() ?? $product->getId());

        $page->setReviews($reviews);

        $page->setCrossSellings(
            $this->crossSellingLoader->load($product->getId(), $salesChannelContext)
        );

        /** @var string $cmsPageId */
        $cmsPageId = $product->getCmsPageId();

        if (Feature::isActive('FEATURE_NEXT_10078') && $cmsPageId !== null && $cmsPage = $this->getCmsPage($cmsPageId, $salesChannelContext)) {
            $this->loadSlotData($cmsPage, $salesChannelContext, $product, $request);
            $page->setCmsPage($cmsPage);
        }

        $this->loadOptions($page);
        $this->loadMetaData($page);

        $this->eventDispatcher->dispatch(
            new ProductPageLoadedEvent($page, $salesChannelContext, $request)
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
                if ($groupOptions->has($optionId)) {
                    $options->add($groupOptions->get($optionId));
                }
            }
        }

        $page->setSelectedOptions($options);
    }

    private function loadMetaData(ProductPage $page): void
    {
        $metaInformation = $page->getMetaInformation();

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
        if (!$page->getSections()) {
            return;
        }

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
}
