<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SitemapPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepositoryInterface $categoryRepository,
        SalesChannelRepositoryInterface $productRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SitemapPage
    {
        $page = new SitemapPage();

        $page->setCategories($this->getCategories($salesChannelContext));

        $page->setProducts($this->getProducts($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new SitemapPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getCategories(SalesChannelContext $salesChannelContext): CategoryCollection
    {
        $categoriesCriteria = new Criteria();
        $categoriesCriteria->setLimit(null);

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($categoriesCriteria, $salesChannelContext)->getEntities();

        return $categories;
    }

    private function getProducts(SalesChannelContext $salesChannelContext): ProductCollection
    {
        $productsCriteria = new Criteria();
        $productsCriteria->setLimit(null);

        /** @var ProductCollection $products */
        $products = $this->productRepository->search($productsCriteria, $salesChannelContext)->getEntities();

        return $products;
    }
}
