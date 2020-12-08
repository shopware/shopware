<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AntiJoinSearchTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM product');
    }

    public function testManyToMany(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $blueId = Uuid::randomHex();

        $products = [
            $this->getTaggedProduct($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedProduct($redId, 'red', ['red']),
            $this->getTaggedProduct($greenId, 'green', ['green']),
            $this->getTaggedProduct($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedProduct($blueId, 'blue', ['blue']),
        ];

        $ids = array_column($products, 'id');

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [
            new EqualsFilter('product.tags.name', 'green'),
        ]);
        $criteria = (new Criteria($ids))
            ->addFilter($notGreenFilter);

        $notGreenIds = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenIds);
        static::assertContains($redId, $notGreenIds);
        static::assertContains($blueId, $notGreenIds);
        static::assertCount(3, $notGreenIds);

        $extendedNotGreenFilter = new MultiFilter('AND', [
            new ContainsFilter('name', 'green'),
            $notGreenFilter,
        ]);

        $notGreenExtendedIds = $productRepository->searchIds((new Criteria($ids))->addFilter($extendedNotGreenFilter), Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenExtendedIds);
        static::assertCount(1, $notGreenExtendedIds);

        $notGreenFilter = new NotFilter('OR', [
            new EqualsFilter('product.tags.name', 'green'),
            new EqualsFilter('product.tags.name', 'red'),
        ]);
        $notGreenOrRedIds = $productRepository->searchIds((new Criteria($ids))->addFilter($notGreenFilter), Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenOrRedIds);
        static::assertContains($blueId, $notGreenOrRedIds);
        static::assertCount(2, $notGreenOrRedIds);
    }

    public function testMultipleManyToMany(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $blueId = Uuid::randomHex();

        $products = [
            $this->getTaggedProduct($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedProduct($greenId, 'green', ['green']),
            $this->getTaggedProduct($redId, 'red', ['red']),
            $this->getTaggedProduct($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedProduct($blueId, 'blue', ['blue']),
        ];

        $ids = array_column($products, 'id');

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $notGreenOrRed = new MultiFilter('AND', [
            new NotFilter('OR', [new EqualsFilter('product.tags.name', 'green'), new EqualsFilter('product.tags.name', 'yellow')]),
            new NotFilter('AND', [new EqualsFilter('product.tags.name', 'red')]),
        ]);
        $notGreenOrRedIds = $productRepository->searchIds((new Criteria($ids))->addFilter($notGreenOrRed), Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenOrRedIds);
        static::assertContains($blueId, $notGreenOrRedIds);
        static::assertCount(2, $notGreenOrRedIds);
    }

    public function testManyToManyWithNormalJoin(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $greenBlueId = Uuid::randomHex();

        $products = [
            $this->getTaggedProduct($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedProduct($greenId, 'green', ['green']),
            $this->getTaggedProduct($redId, 'red', ['red']),
            $this->getTaggedProduct($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedProduct($greenBlueId, 'green blue', ['blue', 'green']),
        ];

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $greenButNotRed = new MultiFilter('AND', [
            new MultiFilter('OR', [
                new EqualsFilter('product.name', 'green'),
                new EqualsFilter('product.tags.name', 'green'),
            ]),
            new NotFilter('AND', [new EqualsFilter('product.tags.name', 'red')]),
        ]);

        $greenButNotRedIds = $productRepository->searchIds((new Criteria())->addFilter($greenButNotRed), Context::createDefaultContext())->getIds();

        static::assertContains($greenId, $greenButNotRedIds);
        static::assertContains($greenBlueId, $greenButNotRedIds);
        static::assertCount(2, $greenButNotRedIds);
    }

    public function testManyToManyToMany(): void
    {
        $noProductsId = Uuid::randomHex();
        $noTagsId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redId = Uuid::randomHex();

        $ids = [$noProductsId, $noTagsId, $greenId, $redId];

        $categories = [
            [
                'id' => $noProductsId,
                'name' => 'no products',
            ],
            [
                'id' => $noTagsId,
                'name' => 'not tags',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'no tags'),
                ],
            ],
            [
                'id' => $greenId,
                'name' => 'green',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'not green'),
                    $this->getTaggedProduct(Uuid::randomHex(), 'green', ['green']),
                ],
            ],
            [
                'id' => $redId,
                'name' => 'red',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'red', ['red']),
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');
        $categoryRepository->create($categories, Context::createDefaultContext());

        $nonGreen = new NotFilter('AND', [
            new EqualsFilter('category.products.tags.name', 'green'),
        ]);
        $nonGreenIds = $categoryRepository->searchIds((new Criteria($ids))->addFilter($nonGreen), Context::createDefaultContext())->getIds();

        static::assertContains($noProductsId, $nonGreenIds);
        static::assertContains($noTagsId, $nonGreenIds);
        static::assertContains($redId, $nonGreenIds);
        static::assertCount(3, $nonGreenIds);
    }

    public function testOneToMany(): void
    {
        $noProductsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $ids = [$noProductsId, $redId, $greenId, $redGreenId];

        $manufacturers = [
            [
                'id' => $noProductsId,
                'name' => 'no products',
            ],
            [
                'id' => $redId,
                'name' => 'red',
                'products' => [
                    [
                        'id' => Uuid::randomHex(),
                        'productNumber' => 'red',
                        'name' => 'red',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
            [
                'id' => $greenId,
                'name' => 'green',
                'products' => [
                    [
                        'id' => Uuid::randomHex(),
                        'productNumber' => 'green',
                        'name' => 'green',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
            [
                'id' => $redGreenId,
                'name' => 'red green',
                'products' => [
                    [
                        'id' => Uuid::randomHex(),
                        'productNumber' => 'red green',
                        'name' => 'red green',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $manufacturerRepository */
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
        $manufacturerRepository->create($manufacturers, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [
            new ContainsFilter('product_manufacturer.products.productNumber', 'green'),
        ]);
        $notGreenIds = $manufacturerRepository->searchIds((new Criteria($ids))->addFilter($notGreenFilter), Context::createDefaultContext())->getIds();
        static::assertContains($noProductsId, $notGreenIds);
        static::assertContains($redId, $notGreenIds);
        static::assertCount(2, $notGreenIds);
    }

    public function testTranslatedField(): void
    {
        $onlyGreenId = Uuid::randomHex();
        $greenGruenId = Uuid::randomHex();
        $ids = [$onlyGreenId, $greenGruenId];

        $products = [
            [
                'id' => $onlyGreenId,
                'productNumber' => $onlyGreenId,
                'translations' => [
                    'en-GB' => [
                        'name' => 'green',
                    ],
                ],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $greenGruenId,
                'productNumber' => $greenGruenId,
                'translations' => [
                    'en-GB' => [
                        'name' => 'green',
                    ],
                    'de-DE' => [
                        'name' => 'grün',
                    ],
                ],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
        ];

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [new EqualsFilter('product.name', 'green')]);

        $enGbContext = Context::createDefaultContext();
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGreenFilter), $enGbContext)->getIds();
        static::assertEmpty($ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGreenFilter), $rawDeContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, 2, true);
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGreenFilter), $deContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $notGruenFilter = new NotFilter('AND', [new EqualsFilter('product.name', 'grün')]);

        $enGbContext = Context::createDefaultContext();
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGruenFilter), $enGbContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGruenFilter), $rawDeContext)->getIds();
        static::assertEmpty($ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, 2, true);
        $ids = $productRepository->searchIds((new Criteria($ids))->addFilter($notGruenFilter), $deContext)->getIds();
        static::assertContains($onlyGreenId, $ids);
        static::assertCount(1, $ids);
    }

    public function testManyToManyWithNegatedFilter(): void
    {
        $withoutTagId = Uuid::randomHex();
        $withTagId = Uuid::randomHex();

        $products = [
            $this->getTaggedProduct($withoutTagId, 'not-green hasNoTags'),
            $this->getTaggedProduct($withTagId, 'green', ['green']),
        ];

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $tagsNullFilters = new EqualsFilter('product.tags.id', null);
        $result = $productRepository->searchIds((new Criteria())->addFilter($tagsNullFilters), Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);

        $notTagsNullFilters = new NotFilter('AND', [$tagsNullFilters]);
        $result = $productRepository->searchIds((new Criteria())->addFilter($notTagsNullFilters), Context::createDefaultContext())->getIds();

        static::assertContains($withTagId, $result);
        static::assertNotContains($withoutTagId, $result);
        static::assertCount(1, $result);

        $notNotTagsNullFilters = new NotFilter('AND', [$notTagsNullFilters]);
        $result = $productRepository->searchIds((new Criteria())->addFilter($notNotTagsNullFilters), Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);
    }

    private function getTaggedProduct(string $id, string $name, array $tags = []): array
    {
        return [
            'id' => $id,
            'productNumber' => $id,
            'name' => $name,
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'tags' => array_map(static function (string $tag) {
                return ['name' => $tag];
            }, $tags),
        ];
    }
}
