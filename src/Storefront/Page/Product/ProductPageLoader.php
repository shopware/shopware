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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader;
use Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

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
     * @var ProductPageConfiguratorLoader
     */
    private $configuratorLoader;

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
        GenericPageLoader $genericLoader,
        SalesChannelRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelCmsPageRepository $cmsPageRepository,
        CmsSlotsDataResolver $slotDataResolver,
        ProductPageConfiguratorLoader $configuratorLoader,
        ProductDefinition $productDefinition,
        ProductLoader $productLoader,
        ProductReviewLoader $productReviewLoader,
        CrossSellingLoader $crossSellingLoader
    ) {
        $this->genericLoader = $genericLoader;
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->configuratorLoader = $configuratorLoader;
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

        $productId = $this->findBestVariant($productId, $salesChannelContext);

        $product = $this->productLoader->load($productId, $salesChannelContext);
        $page->setProduct($product);

        $request->request->set('parentId', $product->getParentId());
        $reviews = $this->productReviewLoader->load($request, $salesChannelContext);
        $reviews->setParentId($product->getParentId() ?? $product->getId());

        $page->setReviews($reviews);

        $page->setConfiguratorSettings(
            $this->configuratorLoader->load($product, $salesChannelContext)
        );

        $page->setCrossSellings(
            $this->crossSellingLoader->load($product->getId(), $salesChannelContext)
        );

        if ($cmsPage = $this->getCmsPage($salesChannelContext)) {
            $this->loadSlotData($cmsPage, $salesChannelContext, $product);
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

        $metaDescription = $page->getProduct()->getMetaDescription()
            ?? $page->getProduct()->getDescription();
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaInformation->setMetaKeywords((string) $page->getProduct()->getKeywords());

        if ((string) $page->getProduct()->getMetaTitle() !== '') {
            $metaInformation->setMetaTitle((string) $page->getProduct()->getMetaTitle());

            return;
        }

        $metaTitleParts = [$page->getProduct()->getTranslation('name')];

        foreach ($page->getSelectedOptions() as $option) {
            $metaTitleParts[] = $option->getName();
        }

        $metaTitleParts[] = $page->getProduct()->getProductNumber();

        $metaInformation->setMetaTitle(implode(' | ', $metaTitleParts));
    }

    private function loadSlotData(
        CmsPageEntity $page,
        SalesChannelContext $salesChannelContext,
        SalesChannelProductEntity $product
    ): void {
        if (!$page->getSections()) {
            return;
        }

        // replace actual request in NEXT-1539
        $request = new Request();

        $resolverContext = new EntityResolverContext($salesChannelContext, $request, $this->productDefinition, $product);

        foreach ($page->getSections() as $section) {
            $slots = $this->slotDataResolver->resolve($section->getBlocks()->getSlots(), $resolverContext);
            $section->getBlocks()->setSlots($slots);
        }
    }

    private function getCmsPage(SalesChannelContext $context): ?CmsPageEntity
    {
        $pages = $this->cmsPageRepository->getPagesByType('product_detail', $context);

        if ($pages->count() === 0) {
            return null;
        }

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function findBestVariant(string $productId, SalesChannelContext $salesChannelContext)
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->addSorting(new FieldSorting('product.price'))
            ->setLimit(1);

        $variantId = $this->productRepository->searchIds($criteria, $salesChannelContext);

        if (\count($variantId->getIds()) > 0) {
            return $variantId->getIds()[0];
        }

        return $productId;
    }
}
