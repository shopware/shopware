<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Demodata\Generator\ProductGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductGenerator::class)]
class ProductGeneratorTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tagIds;

    /**
     * @var list<string>
     */
    private array $ruleIds;

    /**
     * @var list<string>
     */
    private array $manufacturerIds;

    /**
     * @var list<string>
     */
    private array $salesChannelIds;

    private string $instantDeliveryId;

    protected function setUp(): void
    {
        $this->tagIds = array_map(static fn () => Uuid::randomHex(), range(1, 9));
        $this->ruleIds = [Uuid::randomHex()];
        $this->manufacturerIds = [Uuid::randomHex()];
        $this->salesChannelIds = [Uuid::randomHex(), Uuid::randomHex()];
        $this->instantDeliveryId = Uuid::randomHex();
    }

    public function testProductGeneration(): void
    {
        $productCount = 41;

        $tagIds = $this->tagIds;
        $ruleIds = $this->ruleIds;
        $salesChannelIds = $this->salesChannelIds;
        $instantDeliveryId = $this->instantDeliveryId;

        $properties = [
            [
                'id' => Uuid::randomHex(),
            ],
        ];

        $categoryIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $mediaIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $mediaProductDownloadIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $inheritanceUpdater = $this->createMock(InheritanceUpdater::class);
        $inheritanceUpdater->expects($this->exactly(3))->method('update');

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->exactly(3))->method('update');

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('progressStart')->with($productCount);
        $io->expects($this->exactly((int) ($productCount / 20)))->method('progressAdvance');
        $io->expects($this->once())->method('progressFinish');

        $products = $this->generateProducts(
            $productCount,
            categoryUsage: array_fill_keys($categoryIds, 0),
            mediaIds: $mediaIds,
            downloadMediaIds: $mediaProductDownloadIds,
            optionIds: array_column($properties, 'id'),
            inheritanceUpdater: $inheritanceUpdater,
            statesUpdater: $statesUpdater,
            io: $io,
        );

        static::assertCount($productCount, $products);

        foreach ($products as $product) {
            static::assertNotNull($product['id']);
            static::assertIsString($product['productNumber']);
            static::assertStringStartsWith('SW_', $product['productNumber']);
            static::assertIsArray($product['price']);
            static::assertIsArray($product['price'][0]);
            static::assertIsString($product['price'][0]['currencyId']);
            static::assertIsFloat($product['price'][0]['gross']);
            static::assertIsFloat($product['price'][0]['net']);
            static::assertIsBool($product['price'][0]['linked']);
            static::assertIsArray($product['purchasePrices']);
            static::assertIsArray($product['purchasePrices'][0]);
            static::assertIsString($product['purchasePrices'][0]['currencyId']);
            static::assertIsFloat($product['purchasePrices'][0]['gross']);
            static::assertIsFloat($product['purchasePrices'][0]['net']);
            static::assertIsBool($product['purchasePrices'][0]['linked']);
            static::assertIsString($product['name']);
            static::assertIsString($product['description']);
            static::assertIsString($product['taxId']);
            static::assertIsString($product['manufacturerId']);
            static::assertIsBool($product['active']);
            static::assertIsInt($product['height']);
            static::assertIsInt($product['width']);
            static::assertIsArray($product['categories']);

            foreach ($product['categories'] as $category) {
                static::assertContains($category['id'], $categoryIds);
            }

            static::assertIsArray($product['tags']);

            foreach ($product['tags'] as $tag) {
                static::assertContains($tag['id'], $tagIds);
            }

            static::assertIsInt($product['stock']);
            static::assertIsArray($product['prices']);

            if ($product['prices'] !== []) {
                foreach ($product['prices'] as $price) {
                    static::assertContains($price['ruleId'], $ruleIds);
                    static::assertIsInt($price['quantityStart']);

                    if (\array_key_exists('quantityEnd', $price)) {
                        static::assertIsInt($price['quantityEnd']);
                    }

                    static::assertIsArray($price['price']);
                    static::assertIsArray($price['price'][0]);
                    static::assertIsString($price['price'][0]['currencyId']);
                    static::assertIsFloat($price['price'][0]['gross']);
                    static::assertIsFloat($price['price'][0]['net']);
                    static::assertFalse($price['price'][0]['linked']);
                }
            }

            static::assertIsArray($product['visibilities']);

            foreach ($product['visibilities'] as $visibility) {
                static::assertContains($visibility['salesChannelId'], $salesChannelIds);
                static::assertSame(30, $visibility['visibility']);
            }

            static::assertIsArray($product['cover']);
            static::assertIsString($product['cover']['mediaId']);
            static::assertContains($product['cover']['mediaId'], $mediaIds);
            static::assertIsArray($product['media']);

            foreach ($product['media'] as $media) {
                static::assertContains($media['mediaId'], $mediaIds);
            }

            static::assertIsArray($product['properties']);
            static::assertIsArray($product['properties'][0]);
            static::assertIsString($product['properties'][0]['id']);
            static::assertSame($properties[0]['id'], $product['properties'][0]['id']);

            if (\array_key_exists('children', $product)) {
                static::assertIsArray($product['children']);
                static::assertIsArray($child = $product['children'][0]);

                static::assertIsString($child['id']);
                static::assertIsString($child['productNumber']);
                static::assertStringStartsWith('SW_', $product['productNumber']);
                static::assertIsArray($child['price']);
                static::assertIsArray($child['price'][0]);
                static::assertIsString($child['price'][0]['currencyId']);
                static::assertIsFloat($child['price'][0]['gross']);
                static::assertIsFloat($child['price'][0]['net']);
                static::assertIsBool($child['price'][0]['linked']);
                static::assertIsBool($child['active']);
                static::assertIsInt($child['stock']);
                static::assertIsArray($child['prices']);

                if ($child['prices'] !== []) {
                    foreach ($child['prices'] as $price) {
                        static::assertContains($price['ruleId'], $ruleIds);
                        static::assertIsInt($price['quantityStart']);

                        if (\array_key_exists('quantityEnd', $price)) {
                            static::assertIsInt($price['quantityEnd']);
                        }

                        static::assertIsArray($price['price']);
                        static::assertIsArray($price['price'][0]);
                        static::assertIsString($price['price'][0]['currencyId']);
                        static::assertIsFloat($price['price'][0]['gross']);
                        static::assertIsFloat($price['price'][0]['net']);
                        static::assertFalse($price['price'][0]['linked']);
                    }
                }

                static::assertIsArray($child['options']);
                static::assertIsArray($child['options'][0]);
                static::assertIsString($child['options'][0]['id']);
                static::assertSame($properties[0]['id'], $child['options'][0]['id']);
            }

            if (\array_key_exists('configuratorSettings', $product)) {
                static::assertIsArray($product['configuratorSettings']);
                static::assertIsArray($product['configuratorSettings'][0]);
                static::assertIsString($product['configuratorSettings'][0]['optionId']);
                static::assertSame($properties[0]['id'], $product['configuratorSettings'][0]['optionId']);
            }

            if (\array_key_exists('downloads', $product)) {
                static::assertIsArray($product['downloads']);

                foreach ($product['downloads'] as $download) {
                    static::assertIsString($download['id']);
                    static::assertContains($download['mediaId'], $mediaProductDownloadIds);
                    static::assertIsInt($download['position']);
                }

                static::assertSame(1, $product['maxPurchase']);
                static::assertSame($instantDeliveryId, $product['deliveryTimeId']);
            }
        }
    }

    public function testAssignsProductsToTheLeastUsedLeafCategories(): void
    {
        $crowdedCategoryId = Uuid::randomHex();
        $emptyCategoryIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $products = $this->generateProducts(
            20,
            categoryUsage: [$crowdedCategoryId => 100, ...array_fill_keys($emptyCategoryIds, 0)],
        );

        $assigned = [];
        foreach ($products as $product) {
            static::assertIsArray($product['categories']);
            $assigned = [...$assigned, ...array_column($product['categories'], 'id')];
        }

        static::assertEqualsCanonicalizing($emptyCategoryIds, array_values(array_unique($assigned)));
    }

    public function testUsesTheAvailableMediaWhenThereAreFewerThanAProductRequests(): void
    {
        $mediaId = Uuid::randomHex();
        $downloadMediaId = Uuid::randomHex();

        $products = $this->generateProducts(41, mediaIds: [$mediaId], downloadMediaIds: [$downloadMediaId]);

        static::assertIsArray($products[0]['media']);
        static::assertSame([$mediaId], array_column($products[0]['media'], 'mediaId'));
        static::assertIsArray($products[20]['downloads']);
        static::assertSame([$downloadMediaId], array_column($products[20]['downloads'], 'mediaId'));
    }

    public function testCreatesOnlyPhysicalProductsWithoutDownloadMedia(): void
    {
        $products = $this->generateProducts(41);

        foreach ($products as $product) {
            static::assertArrayNotHasKey('downloads', $product);
            static::assertSame(ProductDefinition::TYPE_PHYSICAL, $product['type']);
        }
    }

    public function testAssignsFourToTenDistinctPropertyOptions(): void
    {
        $optionIds = array_map(static fn () => Uuid::randomHex(), range(1, 20));

        $products = $this->generateProducts(41, optionIds: $optionIds);

        foreach ($products as $product) {
            static::assertIsArray($product['properties']);
            $assigned = array_column($product['properties'], 'id');

            static::assertGreaterThanOrEqual(4, \count($assigned));
            static::assertLessThanOrEqual(10, \count($assigned));
            static::assertSame(array_values(array_unique($assigned)), $assigned);
            static::assertSame([], array_diff($assigned, $optionIds));
        }
    }

    /**
     * @param array<string, int> $categoryUsage
     * @param list<string> $mediaIds
     * @param list<string> $downloadMediaIds
     * @param list<string> $optionIds
     *
     * @return list<array<string, mixed>>
     */
    private function generateProducts(
        int $count,
        array $categoryUsage = [],
        array $mediaIds = [],
        array $downloadMediaIds = [],
        array $optionIds = [],
        ?InheritanceUpdater $inheritanceUpdater = null,
        ?StatesUpdater $statesUpdater = null,
        ?SymfonyStyle $io = null,
    ): array {
        $propertyGroupId = Uuid::randomHex();
        $salesChannelIds = $this->salesChannelIds;

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static fn (string $sql): array => match (true) {
                \str_contains($sql, 'sales_channel') => array_map(static fn (string $id) => ['id' => $id], $salesChannelIds),
                \str_contains($sql, 'property_group_option') => array_map(static fn (string $id) => ['id' => $id, 'property_group_id' => $propertyGroupId], $optionIds),
                default => [],
            });
        $connection->method('fetchAllKeyValue')->willReturn($categoryUsage);
        $connection->method('fetchFirstColumn')->willReturn($this->ruleIds, $this->manufacturerIds, $this->tagIds);
        $connection->method('fetchOne')->willReturn($this->instantDeliveryId);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback) => $callback($connection));

        $taxEntity = (new TaxEntity())
            ->assign([
                '_uniqueIdentifier' => 'tax_0',
                'taxRate' => 10,
                'id' => Uuid::randomHex(),
            ]);

        $taxRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'tax',
                1,
                new TaxCollection([$taxEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);
        $mediaRepository = new StaticEntityRepository([$mediaIds, $downloadMediaIds]);
        $productRepository = new StaticEntityRepository([]);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnCallback(static fn (string $entity) => match ($entity) {
            'tax' => $taxRepository,
            'media' => $mediaRepository,
            default => $productRepository,
        });

        $faker = Factory::create();
        $faker->addProvider(new Commerce($faker));

        $context = static::createStub(DemodataContext::class);
        $context->method('getFaker')->willReturn($faker);
        $context->method('getConsole')->willReturn($io ?? static::createStub(SymfonyStyle::class));

        $productGenerator = new ProductGenerator(
            $connection,
            $registry,
            $inheritanceUpdater ?? static::createStub(InheritanceUpdater::class),
            $statesUpdater ?? static::createStub(StatesUpdater::class),
        );
        $productGenerator->generate($count, $context);

        /** @var list<array<string, mixed>> $products */
        $products = array_merge(...$productRepository->creates);

        return $products;
    }
}
