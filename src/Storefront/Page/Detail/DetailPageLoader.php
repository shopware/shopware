<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Content\Product\Storefront\StorefrontProductStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\HttpFoundation\Request;

class DetailPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $configuratorRepository;

    public function __construct(
        StorefrontProductRepository $productRepository,
        RepositoryInterface $configuratorRepository
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

        /** @var StorefrontProductStruct $product */
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
        $selection = array_filter($request->get('group', []));

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

    private function loadConfigurator(StorefrontProductStruct $product, CheckoutContext $context): ProductConfiguratorCollection
    {
        $containerId = $product->getParentId() ?? $product->getId();

        $criteria = new ReadCriteria([]);
        $criteria->addFilter(new EqualsFilter('product_configurator.productId', $containerId));

        /** @var ProductConfiguratorCollection $configurator */
        $configurator = $this->configuratorRepository->read($criteria, $context->getContext());
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
