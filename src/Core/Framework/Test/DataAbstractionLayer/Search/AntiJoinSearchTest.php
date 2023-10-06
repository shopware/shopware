<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AntiJoinSearchTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');
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

        /** @var EntityRepository $productRepository */
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

        $criteria = (new Criteria($ids))->addFilter($extendedNotGreenFilter);

        $notGreenExtendedIds = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenExtendedIds);
        static::assertCount(1, $notGreenExtendedIds);

        $notGreenFilter = new NotFilter('OR', [
            new EqualsFilter('product.tags.name', 'green'),
            new EqualsFilter('product.tags.name', 'red'),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $notGreenOrRedIds = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

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

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $notGreenOrRed = new MultiFilter('AND', [
            new NotFilter('OR', [new EqualsFilter('product.tags.name', 'green'), new EqualsFilter('product.tags.name', 'yellow')]),
            new NotFilter('AND', [new EqualsFilter('product.tags.name', 'red')]),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenOrRed);
        $notGreenOrRedIds = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

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

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $greenButNotRed = new MultiFilter('AND', [
            new MultiFilter('OR', [
                new EqualsFilter('product.name', 'green'),
                new EqualsFilter('product.tags.name', 'green'),
            ]),
            new NotFilter('AND', [new EqualsFilter('product.tags.name', 'red')]),
        ]);

        $criteria = (new Criteria())->addFilter($greenButNotRed);

        $greenButNotRedIds = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($greenId, $greenButNotRedIds);
        static::assertContains($greenBlueId, $greenButNotRedIds);
        static::assertCount(2, $greenButNotRedIds);
    }

    public function testManyToManyToMany(): void
    {
        $ids = new IdsCollection();

        $categories = [
            [
                'id' => $ids->get('no-products'),
                'name' => 'no products',
            ],
            [
                'id' => $ids->get('no-tags'),
                'name' => 'not tags',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'no tags'),
                ],
            ],
            [
                'id' => $ids->get('with-green'),
                'name' => 'green',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'not green'),
                    $this->getTaggedProduct(Uuid::randomHex(), 'green', ['green']),
                ],
            ],
            [
                'id' => $ids->get('with-red'),
                'name' => 'red',
                'products' => [
                    $this->getTaggedProduct(Uuid::randomHex(), 'red', ['red']),
                ],
            ],
        ];

        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');
        $categoryRepository->create($categories, Context::createDefaultContext());

        $nonGreen = new NotFilter('AND', [
            new EqualsFilter('category.products.tags.name', 'green'),
        ]);
        $criteria = (new Criteria($ids->all()))->addFilter($nonGreen);

        $matches = $categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($ids->get('no-products'), $matches);
        static::assertContains($ids->get('no-tags'), $matches);
        static::assertContains($ids->get('with-red'), $matches);
        static::assertCount(3, $matches);
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

        /** @var EntityRepository $manufacturerRepository */
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
        $manufacturerRepository->create($manufacturers, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [
            new ContainsFilter('product_manufacturer.products.productNumber', 'green'),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $notGreenIds = $manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
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

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [new EqualsFilter('product.name', 'green')]);

        $enGbContext = Context::createDefaultContext();
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $enGbContext)->getIds();
        static::assertEmpty($ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $criteria = (new Criteria())->addFilter($notGreenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $rawDeContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $deContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $notGruenFilter = new NotFilter('AND', [new EqualsFilter('product.name', 'grün')]);

        $enGbContext = Context::createDefaultContext();
        $criteria = (new Criteria($ids))->addFilter($notGruenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $enGbContext)->getIds();
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $criteria = (new Criteria($ids))->addFilter($notGruenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $rawDeContext)->getIds();
        static::assertEmpty($ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true);
        $criteria = (new Criteria())->addFilter($notGruenFilter);

        /** @var string[] $ids */
        $ids = $productRepository->searchIds($criteria, $deContext)->getIds();
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

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create($products, Context::createDefaultContext());

        $tagsNullFilters = new EqualsFilter('product.tags.id', null);
        $criteria = (new Criteria())->addFilter($tagsNullFilters);

        $result = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);

        $notTagsNullFilters = new NotFilter('AND', [$tagsNullFilters]);
        $criteria = (new Criteria())->addFilter($notTagsNullFilters);

        $result = $productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($withTagId, $result);
        static::assertNotContains($withoutTagId, $result);
        static::assertCount(1, $result);

        $notNotTagsNullFilters = new NotFilter('AND', [$notTagsNullFilters]);
        $result = $productRepository->searchIds((new Criteria())->addFilter($notNotTagsNullFilters), Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);
    }

    /**
     * @param string[] $tags
     *
     * @return array{
     *     id: string,
     *     productNumber: string,
     *     name: string,
     *     stock: int,
     *     price: array{currencyId: string, gross: int, net: int, linked: bool}[],
     *     manufacturer: array{name: string},
     *     tax: array{name: string, taxRate: int},
     *     tags: array{name: string}[]
     * }
     */
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
            'tags' => array_map(static fn (string $tag) => ['name' => $tag], $tags),
        ];
    }
}
