<?php declare(strict_types=1);

namespace Shopware\Content\Product\Service;

use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Content\Product\Collection\ProductConfiguratorBasicCollection;
use Shopware\Content\Product\Repository\ProductConfiguratorRepository;
use Shopware\Content\Product\Repository\ProductRepository;
use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Content\Product\Struct\ProductConfiguratorBasicStruct;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Content\Product\Exception\NoConfiguratorFoundException;
use Shopware\Content\Product\Exception\ProductNotFoundException;

class VariantGenerator
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ProductConfiguratorRepository
     */
    private $configuratorRepository;

    public function __construct(
        ProductRepository $productRepository,
        ProductConfiguratorRepository $configuratorRepository
    ) {
        $this->productRepository = $productRepository;
        $this->configuratorRepository = $configuratorRepository;
    }

    public function generate(string $productId, ApplicationContext $context, $offset = null, $limit = null): GenericWrittenEvent
    {
        $products = $this->productRepository->readBasic([$productId], $context);
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
            $combinations = array_slice($combinations, $offset, $limit);
        }

        $variants = [];
        foreach ($combinations as $combination) {
            $mapping = array_map(function ($optionId) {
                return ['id' => $optionId];
            }, $combination);

            $options = $configurator->filter(
                function (ProductConfiguratorBasicStruct $config) use ($combination) {
                    return in_array($config->getOptionId(), $combination, true);
                }
            );

            $options->sortByGroup();

            $names = $options->map(function (ProductConfiguratorBasicStruct $config) {
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

    private function loadConfigurator(string $productId, ApplicationContext $context): ProductConfiguratorBasicCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product_configurator.productId', $productId));

        return $this->configuratorRepository->search($criteria, $context);
    }

    private function buildCombinations(ProductConfiguratorBasicCollection $configurator): array
    {
        $groupedOptions = [];
        foreach ($configurator->getOptions() as $option) {
            $groupedOptions[$option->getGroup()->getId()][] = $option->getId();
        }
        $groupedOptions = array_values($groupedOptions);

        return $this->combine($groupedOptions);
    }

    private function combine($data, $group = [], $val = null, $i = 0): array
    {
        $all = [];
        if (isset($val)) {
            $group[] = $val;
        }
        if ($i >= count($data)) {
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

    private function buildPrice(ProductBasicStruct $product, ProductConfiguratorBasicCollection $options): ?array
    {
        $surcharges = $options->fmap(function (ProductConfiguratorBasicStruct $configurator) {
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
