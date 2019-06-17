<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductStreamGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var ProductStreamDefinition
     */
    private $productStreamDefinition;

    public function __construct(EntityWriterInterface $writer, ProductStreamDefinition $productStreamDefinition)
    {
        $this->writer = $writer;
        $this->productStreamDefinition = $productStreamDefinition;
    }

    public function getDefinition(): string
    {
        return ProductStreamDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $categories = $context->getIds('category');
        $manufacturer = $context->getIds('product_manufacturer');
        $products = $context->getIds('product');

        $pool = [
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GTE => random_int(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GTE => random_int(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GTE => random_int(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::LTE => random_int(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::LTE => random_int(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::LTE => random_int(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GT => random_int(1, 500), RangeFilter::LT => random_int(500, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GT => random_int(1, 500), RangeFilter::LT => random_int(500, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GT => random_int(1, 500), RangeFilter::LT => random_int(500, 1000)]],
            ['field' => 'stock', 'type' => 'equals', 'value' => '1000'],
            ['field' => 'name', 'type' => 'contains', 'value' => 'Awesome'],
            ['field' => 'categories.id', 'type' => 'equalsAny', 'value' => implode('|', [$categories[random_int(0, \count($categories) - 1)], $categories[random_int(0, \count($categories) - 1)]])],
            ['field' => 'id', 'type' => 'equalsAny', 'value' => implode('|', [$products[random_int(0, \count($products) - 1)], $products[random_int(0, \count($products) - 1)]])],
            ['field' => 'manufacturerId', 'type' => 'equals', 'value' => $manufacturer[random_int(0, \count($manufacturer) - 1)]],
        ];

        $pool[] = ['type' => 'multi', 'queries' => [$pool[random_int(0, \count($pool) - 1)], $pool[random_int(0, \count($pool) - 1)]]];
        $pool[] = ['type' => 'multi', 'operator' => 'OR', 'queries' => [$pool[random_int(0, \count($pool) - 1)], $pool[random_int(0, \count($pool) - 1)]]];

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $filters = [];

            for ($j = 0, $jMax = random_int(1, 5); $j < $jMax; ++$j) {
                $filters[] = array_merge($pool[random_int(0, \count($pool) - 1)], ['position' => $j]);
            }

            $payload[] = [
                'id' => Uuid::randomHex(),
                'name' => $context->getFaker()->productName,
                'description' => $context->getFaker()->text(),
                'filters' => [['type' => 'multi', 'operator' => 'OR', 'queries' => $filters]],
            ];
        }

        $this->writer->insert($this->productStreamDefinition, $payload, WriteContext::createFromContext($context->getContext()));

        $context->getConsole()->progressFinish();
    }
}
