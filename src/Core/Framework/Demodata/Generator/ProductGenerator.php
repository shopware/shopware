<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
class ProductGenerator implements DemodataGeneratorInterface
{
    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly InheritanceUpdater $updater,
        private readonly StatesUpdater $statesUpdater
    ) {
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    /**
     * @param mixed[] $options
     */
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
        $downloadMediaIds = $this->getMediaIds('product_download');

        $ruleIds = $this->getIds('rule');

        $manufacturers = $this->getIds('product_manufacturer');

        $tags = $this->getIds('tag');

        $instantDeliveryId = $this->getInstantDeliveryId();

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
            $product = $this->createSimpleProduct($taxes, $manufacturers, $tags);

            $product['prices'] = $this->faker->randomElement($prices);

            $product['visibilities'] = $visibilities;

            if ($mediaIds) {
                $product['cover'] = ['mediaId' => Random::getRandomArrayElement($mediaIds)];

                $product['media'] = array_map(fn (string $id): array => ['mediaId' => $id], $this->faker->randomElements($mediaIds, random_int(2, 5)));
            }

            $product['properties'] = $this->buildProperties($properties);

            if ($i % 40 === 0) {
                $combination = $this->faker->randomElement($combinations);
                $product = [...$product, ...$this->buildVariants($combination, $prices, $taxes)];
            } elseif ($i % 20 === 0) {
                $product = [...$product, ...$this->buildDownloads($downloadMediaIds, $instantDeliveryId)];
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

    /**
     * @param array<string, list<string>> $properties
     *
     * @return array<array<string, mixed>>
     */
    private function buildCombinations(array $properties): array
    {
        $properties = $this->faker->randomElements($properties, random_int(min(\count($properties), 1), min(\count($properties), 4)));

        $mapped = [];
        // reduce permutation count
        foreach ($properties as $index => $values) {
            $permutations = is_countable($values) ? \count($values) : 0;
            if ($permutations > 4) {
                $permutations = random_int(2, 4);
            }
            $mapped[$index] = $this->faker->randomElements($values, $permutations);
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

    /**
     * @param list<array<string, mixed>> $combinations
     * @param list<list<array<string, mixed>>> $prices
     *
     * @return array<string, mixed>
     */
    private function buildVariants(array $combinations, array $prices, EntitySearchResult $taxes): array
    {
        $configurator = [];

        $variants = [];
        foreach ($combinations as $options) {
            $price = $this->faker->randomFloat(2, 1, 1000);
            /** @var TaxEntity $tax */
            $tax = $taxes->get(array_rand($taxes->getIds()));
            $taxRate = 1 + ($tax->getTaxRate() / 100);

            $id = Uuid::randomHex();
            $variants[] = [
                'id' => $id,
                'productNumber' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
                'active' => true,
                'stock' => $this->faker->numberBetween(1, 50),
                'prices' => $this->faker->randomElement($prices),
                'options' => array_map(fn ($id) => ['id' => $id], $options),
            ];

            $configurator = [...$configurator, ...array_values($options)];
        }

        return [
            'children' => $variants,
            'configuratorSettings' => array_map(fn (string $id) => ['optionId' => $id], array_filter(array_unique($configurator))),
        ];
    }

    /**
     * @param string[][]|string[] $downloadMediaIds
     *
     * @return array<string, mixed>
     */
    private function buildDownloads(array $downloadMediaIds, ?string $instantDeliveryId): array
    {
        $mediaIds = $this->faker->randomElements($downloadMediaIds, random_int(1, 3), false);

        $downloads = [];
        foreach ($mediaIds as $position => $mediaId) {
            $downloads[] = [
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'position' => $position,
            ];
        }

        return [
            'downloads' => $downloads,
            'maxPurchase' => 1,
            'deliveryTimeId' => $instantDeliveryId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->registry->getRepository('product')->create($payload, $context);

        $all = array_column($payload, 'id');
        foreach ($payload as $product) {
            if (!isset($product['children'])) {
                continue;
            }
            $all = [...$all, ...array_column($product['children'], 'id')];
        }

        $this->updater->update(ProductDefinition::ENTITY_NAME, $all, $context);
        $this->statesUpdater->update($all, $context);

        $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    private function getTaxes(Context $context): EntitySearchResult
    {
        return $this->registry->getRepository('tax')->search(new Criteria(), $context);
    }

    /**
     * @param list<string> $manufacturer
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private function createSimpleProduct(
        EntitySearchResult $taxes,
        array $manufacturer,
        array $tags
    ): array {
        $price = $this->faker->randomFloat(2, 1, 1000);
        $purchasePrice = $this->faker->randomFloat(2, 1, 1000);
        /** @var TaxEntity $tax */
        $tax = $taxes->get(array_rand($taxes->getIds()));
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => $purchasePrice, 'net' => $purchasePrice / $taxRate, 'linked' => true]],
            'name' => $this->faker->format('productName'),
            'description' => $this->faker->text(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => $this->faker->numberBetween(1, 1000),
            'width' => $this->faker->numberBetween(1, 1000),
            'categories' => $this->getCategoryIds(),
            'tags' => $this->getTags($tags),
            'stock' => $this->faker->numberBetween(1, 50),
        ];
    }

    /**
     * @param list<string> $rules
     *
     * @return list<array<string, mixed>>
     */
    private function createPrices(array $rules): array
    {
        $prices = [];
        $rules = \array_slice(
            $rules,
            random_int(0, \max(\count($rules) - 3, 1)),
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

    /**
     * @param list<string> $tags
     *
     * @return array<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if (!empty($tags)) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit(), false);

            if (!empty($chosenTags)) {
                $tagAssignments = array_map(
                    fn ($id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return $tagAssignments;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getProperties(): array
    {
        $options = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, LOWER(HEX(property_group_id)) as property_group_id FROM property_group_option LIMIT 5000');

        $grouped = [];
        foreach ($options as $option) {
            $grouped[(string) $option['property_group_id']][] = (string) $option['id'];
        }

        return $grouped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildVisibilities(): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM sales_channel LIMIT 100');

        return array_map(fn ($id) => ['salesChannelId' => $id['id'], 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL], $ids);
    }

    /**
     * @return list<string>
     */
    private function getMediaIds(string $entity = 'product'): array
    {
        $repository = $this->registry->getRepository('media');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolder.defaultFolder.entity', $entity));
        $criteria->setLimit(500);

        /** @var list<string> $ids */
        $ids = $repository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');
    }

    /**
     * @return list<array{id: string}>
     */
    private function getCategoryIds(): array
    {
        /** @var list<array{id: string}> $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(category.id)) as id
            FROM category
             LEFT JOIN product_category pc
               ON pc.category_id = category.id
            WHERE category.child_count = 0
            GROUP BY category.id
            ORDER BY COUNT(pc.product_id) ASC
            LIMIT ' . $this->faker->numberBetween(1, 3));

        return $result;
    }

    /**
     * @param array<string, list<string>> $properties
     *
     * @return array<array{id: string}>
     */
    private function buildProperties(array $properties): array
    {
        $productProperties = [];
        foreach ($properties as $options) {
            $productProperties = array_merge($productProperties, $this->faker->randomElements($options, 3));
        }

        $productProperties = \array_slice($productProperties, 0, random_int(4, 10));

        return array_map(fn ($config) => ['id' => (string) $config], $productProperties);
    }

    private function getInstantDeliveryId(): ?string
    {
        $id = $this->connection->fetchOne('SELECT LOWER(HEX(delivery_time_id)) FROM delivery_time_translation WHERE `name` = "Instant download" LIMIT 1');

        return \is_string($id) ? $id : null;
    }
}
