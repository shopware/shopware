<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorEntity;
use Shopware\Core\Content\Product\Exception\NoConfiguratorFoundException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class VariantGenerator
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $configuratorRepository;

    public function __construct(
        RepositoryInterface $productRepository,
        RepositoryInterface $configuratorRepository
    ) {
        $this->productRepository = $productRepository;
        $this->configuratorRepository = $configuratorRepository;
    }

    public function generate(string $productId, Context $context, $offset = null, $limit = null): EntityWrittenContainerEvent
    {
        $products = $this->productRepository->read(new ReadCriteria([$productId]), $context);
        $product = $products->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        $configurator = $this->loadConfigurator($productId, $context);

        if ($configurator->count() <= 0) {
            throw new NoConfiguratorFoundException($productId);
        }
        $combinations = $this->buildCombinations($configurator);
        if ($offset !== null && $limit !== null) {
            $combinations = \array_slice($combinations, $offset, $limit);
        }

        $variants = [];
        foreach ($combinations as $combination) {
            $mapping = array_map(function ($optionId) {
                return ['id' => $optionId];
            }, $combination);

            $options = $configurator->filter(
                function (ProductConfiguratorEntity $config) use ($combination) {
                    return \in_array($config->getOptionId(), $combination, true);
                }
            );

            $options->sortByGroup();

            $names = $options->map(function (ProductConfiguratorEntity $config) {
                return $config->getOption()->getName();
            });

            $variant = [
                'parentId' => $productId,
                'name' => $product->getName() . ' ' . implode(' ', $names),
                'variations' => $mapping,
                'variationIds' => array_values($options->getOptionIds()),
                'price' => $this->buildPrice($product, $options),
            ];

            $variants[] = array_filter($variant);
        }

        return $this->productRepository->create($variants, $context);
    }

    private function loadConfigurator(string $productId, Context $context): ProductConfiguratorCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_configurator.productId', $productId));

        /** @var ProductConfiguratorCollection $result */
        $result = $this->configuratorRepository->search($criteria, $context)->getEntities();

        return $result;
    }

    private function buildCombinations(ProductConfiguratorCollection $configurator): array
    {
        $groupedOptions = [];
        foreach ($configurator->getOptions() as $option) {
            $groupedOptions[$option->getGroup()->getId()][] = $option->getId();
        }
        $groupedOptions = array_values($groupedOptions);

        return $this->combine($groupedOptions);
    }

    private function combine($data, array $group = [], $val = null, $i = 0): array
    {
        $all = [];
        if ($val !== null) {
            $group[] = $val;
        }
        if ($i >= \count($data)) {
            $all[] = $group;
        } else {
            foreach ($data[$i] as $v) {
                $nested = $this->combine($data, $group, $v, $i + 1);
                foreach ($nested as $item) {
                    $all[] = $item;
                }
            }
        }

        return $all;
    }

    private function buildPrice(ProductEntity $product, ProductConfiguratorCollection $options): ?array
    {
        $surcharges = $options->fmap(function (ProductConfiguratorEntity $configurator) {
            return $configurator->getPrice();
        });

        if (empty($surcharges)) {
            return null;
        }

        $price = clone $product->getPrice();
        foreach ($surcharges as $surcharge) {
            $price->add($surcharge);
        }

        return ['gross' => $price->getGross(), 'net' => $price->getNet()];
    }
}
