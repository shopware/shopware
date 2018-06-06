<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorRepository;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Content\Product\Struct\StorefrontProductDetailStruct;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Symfony\Component\HttpFoundation\Request;

class DetailPageLoader
{
    /**
     * @var \Shopware\Core\Content\Product\Storefront\StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var ProductConfiguratorRepository
     */
    private $configuratorRepository;

    public function __construct(
        StorefrontProductRepository $productRepository,
        ProductConfiguratorRepository $configuratorRepository
    ) {
        $this->productRepository = $productRepository;
        $this->configuratorRepository = $configuratorRepository;
    }

    public function load(string $productId, Request $request, CheckoutContext $context): DetailPageStruct
    {
        $parentId = $this->fetchParentId($productId, $context);

        $productId = $this->resolveProductId($productId, $parentId, $request, $context);

        $collection = $this->productRepository->readDetail([$productId], $context);

        if (!$collection->has($productId)) {
            throw new \RuntimeException('Product was not found.');
        }

        /** @var \Shopware\Core\Content\Product\Struct\StorefrontProductBasicStruct $product */
        $product = $collection->get($productId);

        $page = new DetailPageStruct($product);

        $page->setConfigurator(
            $this->loadConfigurator($product, $context)
        );

        return $page;
    }

    private function resolveProductId(
        string $productId,
        string $parentId,
        Request $request,
        CheckoutContext $context
    ): string {
        $selection = $request->get('group', []);

        $selection = array_filter($selection);

        if (empty($selection)) {
            return $productId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.parentId', $parentId));

        $queries = [];
        foreach ($selection as $groupId => $optionId) {
            $queries[] = new TermQuery('product.variationIds', $optionId);
        }

        $criteria->addFilter(new NestedQuery($queries));
        $criteria->setLimit(1);

        $ids = $this->productRepository->searchIds($criteria, $context);
        $ids = $ids->getIds();

        $first = array_shift($ids);

        if ($first) {
            return $first;
        }

        return $productId;
    }

    private function loadConfigurator(StorefrontProductDetailStruct $product, CheckoutContext $context): ProductConfiguratorBasicCollection
    {
        $containerId = $product->getParentId() ?? $product->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product_configurator.productId', $containerId));

        $configurator = $this->configuratorRepository->search($criteria, $context->getContext());
        $variationIds = $product->getVariationIds() ?? [];

        foreach ($configurator as $config) {
            $config->setSelected(in_array($config->getOptionId(), $variationIds, true));
        }

        return $configurator;
    }

    private function fetchParentId(string $productId, CheckoutContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.children.id', $productId));

        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        if (!empty($ids)) {
            return array_shift($ids);
        }

        return $productId;
    }
}
