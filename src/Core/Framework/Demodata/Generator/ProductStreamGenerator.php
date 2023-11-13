<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class ProductStreamGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly ProductStreamDefinition $productStreamDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return ProductStreamDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $faker = $context->getFaker();

        $pool = [
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::LTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::LTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::LTE => $faker->numberBetween(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GT => $faker->numberBetween(1, 500), RangeFilter::LT => $faker->numberBetween(500, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GT => $faker->numberBetween(1, 500), RangeFilter::LT => $faker->numberBetween(500, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GT => $faker->numberBetween(1, 500), RangeFilter::LT => $faker->numberBetween(500, 1000)]],
            ['field' => 'stock', 'type' => 'equals', 'value' => '1000'],
            ['field' => 'name', 'type' => 'contains', 'value' => 'Awesome'],
            ['field' => 'categoriesRo.id', 'type' => 'equalsAny', 'value' => implode('|', [$context->getRandomId('category'), $context->getRandomId('category')])],
            ['field' => 'id', 'type' => 'equalsAny', 'value' => implode('|', [$context->getRandomId('product'), $context->getRandomId('product')])],
            ['field' => 'manufacturerId', 'type' => 'equals', 'value' => $context->getRandomId('product_manufacturer')],
        ];

        $pool[] = ['type' => 'multi', 'operator' => 'AND', 'queries' => [$faker->randomElement($pool), $faker->randomElement($pool)]];
        $pool[] = ['type' => 'multi', 'operator' => 'OR', 'queries' => [$faker->randomElement($pool), $faker->randomElement($pool)]];

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $filters = [];

            for ($j = 0, $jMax = $faker->numberBetween(1, 5); $j < $jMax; ++$j) {
                $filters[] = array_merge($faker->randomElement($pool), ['position' => $j]);
            }

            $payload[] = [
                'id' => Uuid::randomHex(),
                'name' => $faker->format('productName'),
                'description' => $faker->text(),
                'filters' => [['type' => 'multi', 'operator' => 'OR', 'queries' => $filters]],
            ];
        }

        $this->writer->insert($this->productStreamDefinition, $payload, WriteContext::createFromContext($context->getContext()));

        $context->getConsole()->progressFinish();
    }
}
