<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\Storefront\StorefrontCategoryRepository;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductDetailPageletLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $configuratorRepository;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var StorefrontCategoryRepository
     */
    private $categoryService;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EntityRepositoryInterface $configuratorRepository,
        CartService $cartService,
        StorefrontCategoryRepository $categoryService
    ) {
        $this->productRepository = $productRepository;
        $this->configuratorRepository = $configuratorRepository;
        $this->cartService = $cartService;
        $this->categoryService = $categoryService;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function load(InternalRequest $request, CheckoutContext $context): ProductDetailPageletStruct
    {
        $productId = (string) $request->requireGet('productId');
        $parentId = $this->fetchParentId($productId, $context);

        $productId = $this->resolveProductId($productId, $parentId, $request, $context);

        $collection = $this->productRepository->readDetail([$productId], $context);

        if (!$collection->has($productId)) {
            throw new \RuntimeException('Product was not found.');
        }

        /** @var StorefrontProductEntity $product */
        $product = $collection->get($productId);

        $page = new ProductDetailPageletStruct();
        $page->setProduct($product);

        $page->setConfigurator(
            $this->loadConfigurator($product, $context)
        );

        return $page;
    }

    private function resolveProductId(
        string $productId,
        string $parentId,
        InternalRequest $request,
        CheckoutContext $context
    ): string {
        $selection = array_filter($request->optionalGet('group'));

        if (empty($selection)) {
            return $productId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $parentId));

        $queries = [];
        foreach ($selection as $groupId => $optionId) {
            $queries[] = new EqualsFilter('product.variationIds', $optionId);
        }

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, $queries));
        $criteria->setLimit(1);

        $ids = $this->productRepository->searchIds($criteria, $context);
        $ids = $ids->getIds();

        $first = array_shift($ids);

        if ($first) {
            return $first;
        }

        return $productId;
    }

    private function loadConfigurator(StorefrontProductEntity $product, CheckoutContext $context): ProductConfiguratorCollection
    {
        $containerId = $product->getParentId() ?? $product->getId();

        $criteria = new Criteria([]);
        $criteria->addFilter(new EqualsFilter('product_configurator.productId', $containerId));

        /** @var ProductConfiguratorCollection $configurator */
        $configurator = $this->configuratorRepository->search($criteria, $context->getContext());
        $variationIds = $product->getVariationIds() ?? [];

        foreach ($configurator as $config) {
            $config->setSelected(\in_array($config->getOptionId(), $variationIds, true));
        }

        return $configurator;
    }

    private function fetchParentId(string $productId, CheckoutContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.children.id', $productId));

        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        if (!empty($ids)) {
            return array_shift($ids);
        }

        return $productId;
    }
}
