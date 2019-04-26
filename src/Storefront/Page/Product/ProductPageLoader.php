<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductPageLoader implements PageLoaderInterface
{
    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var SalesChannelCmsPageRepository
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

    /**
     * @var ProductPageConfiguratorLoader
     */
    private $configuratorLoader;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        SalesChannelRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelCmsPageRepository $cmsPageRepository,
        SlotDataResolver $slotDataResolver,
        ProductPageConfiguratorLoader $configuratorLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->productRepository = $productRepository;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->configuratorLoader = $configuratorLoader;
    }

    public function load(Request $request, SalesChannelContext $context): ProductPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);
        $page = ProductPage::createFrom($page);

        $productId = $request->attributes->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId', '/productId');
        }

        $productId = $this->findBestVariant($productId, $context);

        $product = $this->loadProduct($productId, $context);
        $page->setProduct($product);

        $page->setConfiguratorSettings(
            $this->configuratorLoader->load($product, $context)
        );

        if ($cmsPage = $this->getCmsPage($context)) {
            $this->loadSlotData($cmsPage, $context, $product);
            $page->setCmsPage($cmsPage);
        }

        $this->eventDispatcher->dispatch(
            ProductPageLoadedEvent::NAME,
            new ProductPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadSlotData(CmsPageEntity $page, SalesChannelContext $context, SalesChannelProductEntity $product): void
    {
        if (!$page->getBlocks()) {
            return;
        }

        // replace actual request in NEXT-1539
        $request = new Request();

        $resolverContext = new EntityResolverContext($context, $request, ProductDefinition::class, $product);
        $slots = $this->slotDataResolver->resolve($page->getBlocks()->getSlots(), $resolverContext);

        $page->getBlocks()->setSlots($slots);
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

    private function loadProduct(string $productId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));
        $criteria->addAssociation('product.media');
        $criteria->addAssociation('product.prices');

        $criteria->addAssociation('prices');
        $criteria->addAssociation('media');
        $criteria->addAssociation('cover');
        $criteria->addAssociationPath('properties.group');

        $this->eventDispatcher->dispatch(
            ProductPageCriteriaEvent::NAME,
            new ProductPageCriteriaEvent($criteria, $context)
        );

        /** @var SalesChannelProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        $product->setSortedProperties(
            $this->sortProperties($product)
        );

        return $product;
    }

    private function sortProperties(SalesChannelProductEntity $product): PropertyGroupCollection
    {
        $sorted = [];
        foreach ($product->getProperties() as $option) {
            $group = $option->getGroup();

            if (!$group) {
                continue;
            }

            if (!$group->getOptions()) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            $group->getOptions()->add($option);

            $sorted[$group->getId()] = $group;
        }

        usort(
            $sorted,
            function (PropertyGroupEntity $a, PropertyGroupEntity $b) {
                return strnatcmp($a->getName(), $b->getName());
            }
        );

        /** @var PropertyGroupEntity $group */
        foreach ($sorted as $group) {
            $group->getOptions()->sort(
                function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($group) {
                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return strnatcmp($a->getName(), $b->getName());
                    }

                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return $a->getName() <=> $b->getName();
                    }

                    return $a->getPosition() <=> $b->getPosition();
                }
            );
        }

        return new PropertyGroupCollection($sorted);
    }

    private function findBestVariant(string $productId, SalesChannelContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $productId));
        $criteria->addSorting(new FieldSorting('product.price'));
        $criteria->setLimit(1);

        $variantId = $this->productRepository->searchIds($criteria, $context);

        if (count($variantId->getIds()) > 0) {
            return $variantId->getIds()[0];
        }

        return $productId;
    }
}
