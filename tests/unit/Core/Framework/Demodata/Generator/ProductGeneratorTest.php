<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Shopware\Core\Framework\Demodata\Generator\ProductGenerator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(ProductGenerator::class)]
class ProductGeneratorTest extends TestCase
{
    public function testProductGeneration(): void
    {
        $productCount = 41;

        $tagIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $ruleIds = [
            Uuid::randomHex(),
        ];

        $manufacturerIds = [
            Uuid::randomHex(),
        ];

        $salesChannelIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $properties = [
            [
                'id' => Uuid::randomHex(),
                'property_group_id' => Uuid::randomHex(),
            ],
        ];

        $categoryIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $instantDeliveryId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(function () use ($salesChannelIds, $properties, $categoryIds) {
                $sqlStatement = \func_get_arg(0);

                if (\str_contains($sqlStatement, 'sales_channel')) {
                    return \array_map(fn (string $id) => ['id' => $id], $salesChannelIds);
                }

                if (\str_contains($sqlStatement, 'property_group_option')) {
                    return $properties;
                }

                if (\str_contains($sqlStatement, 'category')) {
                    return \array_map(fn (string $id) => ['id' => $id], $categoryIds);
                }

                return null;
            });
        $connection->method('fetchFirstColumn')->willReturn($ruleIds, $manufacturerIds, $tagIds);
        $connection->method('fetchOne')->willReturn($instantDeliveryId);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $taxEntity = (new TaxEntity())
            ->assign([
                '_uniqueIdentifier' => 'tax_0',
                'taxRate' => 10,
                'id' => Uuid::randomHex(),
            ]);

        $taxRepository = new StaticEntityRepository([
            new EntitySearchResult(
                TaxEntity::class,
                1,
                new TaxCollection([$taxEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

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

        $mediaRepository = new StaticEntityRepository([
            new IdSearchResult(
                5,
                \array_map(fn (string $id) => ['primaryKey' => $id, 'data' => []], $mediaIds),
                new Criteria(),
                Context::createDefaultContext()
            ),
            new IdSearchResult(
                5,
                \array_map(fn (string $id) => ['primaryKey' => $id, 'data' => []], $mediaProductDownloadIds),
                new Criteria(),
                Context::createDefaultContext()
            ),
        ]);

        $productRepository = new StaticEntityRepository([]);

        $registry->method('getRepository')->willReturnCallback(function () use ($taxRepository, $mediaRepository, &$productRepository) {
            $entityName = \func_get_arg(0);

            return match ($entityName) {
                'tax' => $taxRepository,
                'media' => $mediaRepository,
                'product' => $productRepository,
                default => null,
            };
        });

        $inheritanceUpdater = $this->createMock(InheritanceUpdater::class);
        $inheritanceUpdater->expects(TestCase::exactly(3))->method('update');

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects(TestCase::exactly(3))->method('update');

        $productGenerator = new ProductGenerator($connection, $registry, $inheritanceUpdater, $statesUpdater);

        $generator = Factory::create();
        $generator->addProvider(new Commerce($generator));

        $context = $this->createMock(DemodataContext::class);
        $context->method('getFaker')->willReturn($generator);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(TestCase::exactly(1))->method('progressStart')->with($productCount);
        $io->expects(TestCase::exactly((int) ($productCount / 20)))->method('progressAdvance');
        $io->expects(TestCase::exactly(1))->method('progressFinish');

        $context->method('getConsole')->willReturn($io);

        $productGenerator->generate($productCount, $context);

        $products = [];

        foreach ($productRepository->creates as $productBatches) {
            $products = [...$products, ...\array_values($productBatches)];
        }

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

            if (\count($product['prices']) > 0) {
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

                if (\count($child['prices']) > 0) {
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
}
