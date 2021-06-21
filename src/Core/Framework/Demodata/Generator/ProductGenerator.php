<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProductGenerator implements DemodataGeneratorInterface
{
    private Connection $connection;

    private SymfonyStyle $io;

    private Generator $faker;

    private DefinitionInstanceRegistry $registry;

    public function __construct(Connection $connection, DefinitionInstanceRegistry $registry)
    {
        $this->connection = $connection;
        $this->registry = $registry;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createProducts($context->getContext(), $numberOfItems);
    }

    private function createProducts(Context $context, int $count): void
    {
        $visibilities = $this->buildVisibilities();

        $taxes = $this->getTaxes($context);

        if ($taxes->count() === 0) {
            throw new \RuntimeException('This demo data command should be executed after the original demo data was executed at least one time');
        }

        $properties = $this->getProperties();

        $this->io->progressStart($count);

        $mediaIds = $this->getMediaIds();

        $ruleIds = $this->getIds('rule');

        $categories = $this->getIds('category');

        $manufacturers = $this->getIds('product_manufacturer');

        $combinations = [];
        for ($i = 0; $i <= 20; ++$i) {
            $combinations[] = $this->buildCombinations($properties);
        }

        $max = max(min($count / 3, 200), 5);
        $prices = [];
        for ($i = 0; $i <= $max; ++$i) {
            $prices[] = $this->createPrices($ruleIds);
        }

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($taxes, $categories, $manufacturers);

            $product['prices'] = $this->faker->randomElement($prices);

            $product['visibilities'] = $visibilities;

            if ($mediaIds) {
                $product['cover'] = ['mediaId' => Random::getRandomArrayElement($mediaIds)];

                $product['media'] = array_map(function (string $id): array {
                    return ['mediaId' => $id];
                }, $this->faker->randomElements($mediaIds, random_int(2, 5)));
            }

            $product['properties'] = $this->buildProperties($properties);

            if ($i % 40 === 0) {
                $combination = $this->faker->randomElement($combinations);
                $product = array_merge($product, $this->buildVariants($combination, $prices, $taxes));
            }

            $payload[] = $product;

            if (\count($payload) >= 20) {
                $this->io->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $this->io->progressFinish();
    }

    private function buildCombinations(array $properties): array
    {
        $properties = $this->faker->randomElements($properties, random_int(2, 4));

        $mapped = [];
        // reduce permutation count
        foreach ($properties as $index => $values) {
            $mapped[$index] = $this->faker->randomElements($values, random_int(2, 4));
        }
        $properties = $mapped;

        $result = [[]];
        foreach ($properties as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    private function buildVariants(array $combinations, array $prices, EntitySearchResult $taxes): array
    {
        $configurator = [];

        $variants = [];
        foreach ($combinations as $options) {
            $price = $this->faker->randomFloat(2, 1, 1000);
            $tax = $taxes->get(array_rand($taxes->getIds()));
            $taxRate = 1 + ($tax->getTaxRate() / 100);

            $variants[] = [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
                'active' => true,
                'stock' => $this->faker->numberBetween(1, 50),
                'prices' => $this->faker->randomElement($prices),
                'options' => array_map(function ($id) {
                    return ['id' => $id];
                }, $options),
            ];

            $configurator = array_merge($configurator, array_values($options));
        }

        return [
            'children' => $variants,
            'configuratorSettings' => array_map(function (string $id) {
                return ['optionId' => $id];
            }, array_filter(array_unique($configurator))),
        ];
    }

    private function write(array $payload, Context $context): void
    {
        $context->addExtension(EntityIndexerRegistry::DISABLE_INDEXING, new ArrayStruct());

        $this->registry->getRepository('product')->create($payload, $context);

        $context->removeExtension(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    private function getTaxes(Context $context): EntitySearchResult
    {
        return $this->registry->getRepository('tax')->search(new Criteria(), $context);
    }

    private function createSimpleProduct(
        EntitySearchResult $taxes,
        array $categories,
        array $manufacturer
    ): array {
        $price = $this->faker->randomFloat(2, 1, 1000);
        $tax = $taxes->get(array_rand($taxes->getIds()));
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
            'name' => $this->faker->productName,
            'description' => $this->faker->text(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => $this->faker->numberBetween(1, 1000),
            'width' => $this->faker->numberBetween(1, 1000),
            'categories' => [
                ['id' => $this->faker->randomElement($categories)],
            ],
            'stock' => $this->faker->numberBetween(1, 50),
        ];
    }

    private function createPrices(array $rules): array
    {
        $prices = [];
        $rules = \array_slice(
            $rules,
            random_int(0, \count($rules) - 3),
            random_int(1, 3)
        );

        $values = [];
        for ($i = 1; $i <= 200; ++$i) {
            $value = $this->faker->randomFloat(2, $i * 10, $i * 100);

            $values[] = [
                $value,
                round($value / 100 * (random_int(50, 90)), 2),
            ];
        }

        foreach ($rules as $ruleId) {
            $price = $this->faker->randomElement($values);

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[0], 'net' => $price[0] / 119, 'linked' => false]],
            ];

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 11,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[1], 'net' => $price[1] / 119, 'linked' => false]],
            ];
        }

        return $prices;
    }

    private function getProperties(): array
    {
        $options = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id, LOWER(HEX(property_group_id)) as property_group_id FROM property_group_option LIMIT 5000');

        $grouped = [];
        foreach ($options as $option) {
            $grouped[$option['property_group_id']][] = $option['id'];
        }

        return $grouped;
    }

    private function buildVisibilities(): array
    {
        $ids = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM sales_channel LIMIT 100');

        return array_map(function ($id) {
            return ['salesChannelId' => $id['id'], 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL];
        }, $ids);
    }

    private function getMediaIds(): array
    {
        $repository = $this->registry->getRepository('media');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolder.defaultFolder.entity', 'product'));
        $criteria->setLimit(500);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    private function getIds(string $table): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');

        return array_column($ids, 'id');
    }

    private function buildProperties(array $properties): array
    {
        $productProperties = [];
        foreach ($properties as $options) {
            $productProperties = array_merge($productProperties, $this->faker->randomElements($options, 3));
        }

        $productProperties = \array_slice($productProperties, 0, random_int(4, 10));

        return array_map(function ($config) {
            return ['id' => $config];
        }, $productProperties);
    }
}
