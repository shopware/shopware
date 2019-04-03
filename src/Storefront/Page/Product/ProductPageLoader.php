<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Content\Cms\Storefront\StorefrontCmsPageRepository;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductPageLoader implements PageLoaderInterface
{
    /**
     * @var StorefrontProductRepository
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
     * @var StorefrontCmsPageRepository
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        StorefrontCmsPageRepository $cmsPageRepository,
        SlotDataResolver $slotDataResolver
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->productRepository = $productRepository;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    public function load(InternalRequest $request, SalesChannelContext $context): ProductPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);
        $page = ProductPage::createFrom($page);

        $productId = $request->requireGet('productId');
        $product = $this->loadProduct($productId, $context);
        $page->setProduct($product);

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

    private function loadSlotData(CmsPageEntity $page, SalesChannelContext $context, StorefrontProductEntity $product): void
    {
        if (!$page->getBlocks()) {
            return;
        }

        $resolverContext = new EntityResolverContext($context, ProductDefinition::class, $product);
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

    private function loadProduct(string $productId, SalesChannelContext $context): StorefrontProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));

        /** @var StorefrontProductEntity|null $product */
        $product = $this->productRepository->read($criteria, $context)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        return $product;
    }
}
