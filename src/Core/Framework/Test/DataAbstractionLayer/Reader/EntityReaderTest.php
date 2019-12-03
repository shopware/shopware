<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Reader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxEntity;

class EntityReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EntityRepository
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var string
     */
    private $deLanguageId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testTranslated(): void
    {
        $id = Uuid::randomHex();
        $data = [
            ['id' => $id, 'name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create($data, $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                Defaults::LANGUAGE_SYSTEM,
                $this->deLanguageId,
            ]
        );

        $categories = $this->categoryRepository->search(new Criteria([$id]), $context);
        /** @var CategoryEntity $category */
        $category = $categories->first();

        static::assertSame('test', $category->getName());
        static::assertSame('test', $category->getTranslated()['name']);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                $this->deLanguageId,
                Defaults::LANGUAGE_SYSTEM,
            ]
        );

        $categories = $this->categoryRepository->search(new Criteria([$id]), $context);
        /** @var CategoryEntity $category */
        $category = $categories->first();

        static::assertNull($category->getName());
        static::assertSame('test', $category->getTranslated()['name']);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                $this->deLanguageId,
            ]
        );

        $categories = $this->categoryRepository->search(new Criteria([$id]), $context);
        /** @var CategoryEntity $category */
        $category = $categories->first();

        static::assertNull($category->getName());
        static::assertNull($category->getTranslated()['name']);
    }

    public function testTranslatedFieldsContainsNoInheritance(): void
    {
        $id = Uuid::randomHex();

        $subLanguageId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
            ],
        ], $context);

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'EN'],
                $this->deLanguageId => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$subLanguageId, Defaults::LANGUAGE_SYSTEM]
        );

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertNull($product->getName());
        static::assertEquals('test', $product->getDescription());
    }

    public function testInheritedTranslationsInViewData(): void
    {
        $id = Uuid::randomHex();

        $subLanguageId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
            ],
        ], $context);

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'EN'],
                $this->deLanguageId => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$subLanguageId, Defaults::LANGUAGE_SYSTEM]
        );

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertEquals('EN', $product->getTranslated()['name']);
        static::assertEquals('test', $product->getTranslated()['description']);
    }

    public function testParentInheritanceInViewData(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentTax = Uuid::randomHex();
        $greenTax = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => true]],
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'parent tax'],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => true]],
                'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green tax'],
            ],
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$parentId, $greenId, $redId]);
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(false);
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(TaxEntity::class, $parent->getTax());
        static::assertInstanceOf(Price::class, $parent->getCurrencyPrice(Defaults::CURRENCY));
        static::assertEquals(50, $parent->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        //check red product contains full inheritance of parent
        static::assertInstanceOf(ProductEntity::class, $red);

        //has no own tax
        static::assertNull($red->getTax());
        static::assertNull($red->getTaxId());
        static::assertNull($red->getCurrencyPrice(Defaults::CURRENCY));

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertInstanceOf(ProductEntity::class, $green);
        static::assertInstanceOf(TaxEntity::class, $green->getTax());
        static::assertEquals($greenTax, $green->getTaxId());
        static::assertInstanceOf(Price::class, $green->getCurrencyPrice(Defaults::CURRENCY));
        static::assertEquals(100, $green->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        $criteria = new Criteria([$parentId, $greenId, $redId]);
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(TaxEntity::class, $parent->getTax());
        static::assertInstanceOf(Price::class, $parent->getCurrencyPrice(Defaults::CURRENCY));
        static::assertEquals(50, $parent->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        //check red product contains full inheritance of parent
        static::assertInstanceOf(ProductEntity::class, $red);

        //price and tax are inherited by parent
        static::assertInstanceOf(Price::class, $red->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(TaxEntity::class, $red->getTax());
        static::assertEquals($parentTax, $red->getTaxId());
        static::assertInstanceOf(Price::class, $red->getCurrencyPrice(Defaults::CURRENCY));
        static::assertEquals(50, $red->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        /** @var ProductEntity $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductEntity::class, $green);
        static::assertInstanceOf(TaxEntity::class, $green->getTax());
        static::assertEquals($greenTax, $green->getTaxId());
        static::assertInstanceOf(Price::class, $green->getCurrencyPrice(Defaults::CURRENCY));
        static::assertEquals(100, $green->getCurrencyPrice(Defaults::CURRENCY)->getGross());
    }

    public function testInheritanceWithOneToMany(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                    ],
                    [
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('prices');
        $context->setConsiderInheritance(true);

        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(ProductPriceCollection::class, $parent->getPrices());

        /** @var ProductEntity $red */
        $red = $products->get($redId);
        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertCount(2, $red->getPrices());
        static::assertInstanceOf(ProductPriceCollection::class, $red->getPrices());

        /** @var ProductEntity $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductEntity::class, $green);
        static::assertInstanceOf(ProductPriceCollection::class, $green->getPrices());
    }

    public function testInheritanceWithPaginatedOneToMany(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                    ],
                    [
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->getAssociation('prices')->setLimit(5);
        $context->setConsiderInheritance(true);

        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(ProductPriceCollection::class, $parent->getPrices());

        /** @var ProductEntity $red */
        $red = $products->get($redId);
        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertCount(2, $red->getPrices());

        /** @var ProductEntity $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductEntity::class, $green);
        static::assertInstanceOf(ProductPriceCollection::class, $green->getPrices());
    }

    public function testInheritanceWithManyToMany(): void
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category3],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories');
        $context->setConsiderInheritance(true);

        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        /** @var ProductEntity $red */
        $red = $products->get($redId);
        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertInstanceOf(ProductEntity::class, $green);

        //validate parent view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertCount(2, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate red view data contains the categories of the parent
        static::assertCount(2, $red->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertTrue($red->getCategories()->has($category1));
        static::assertTrue($red->getCategories()->has($category3));

        //validate green view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertCount(1, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        //####
        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories');
        $context->setConsiderInheritance(false);

        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertInstanceOf(ProductEntity::class, $green);

        //validate parent contains own categories
        static::assertCount(2, $parent->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate red contains no own categories
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertCount(0, $red->getCategories());

        //validate green contains own categories
        static::assertCount(1, $green->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));
    }

    public function testInheritanceWithPaginatedManyToMany(): void
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category3],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories')->setLimit(3);
        $context->setConsiderInheritance(true);
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        /** @var ProductEntity $red */
        $red = $products->get($redId);
        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertInstanceOf(ProductEntity::class, $parent);
        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertInstanceOf(ProductEntity::class, $green);

        //validate parent view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertCount(2, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate red view data contains the categories of the parent
        static::assertCount(2, $red->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertTrue($red->getCategories()->has($category1));
        static::assertTrue($red->getCategories()->has($category3));

        //validate green view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertCount(1, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories')->setLimit(3);
        $context->setConsiderInheritance(false);
        $products = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $parent */
        $parent = $products->get($parentId);
        /** @var ProductEntity $red */
        $red = $products->get($redId);
        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        //validate parent contains own categories
        static::assertCount(2, $parent->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate green contains own categories
        static::assertCount(1, $green->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        //validate red contains no own categories
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertCount(0, $red->getCategories());
    }

    public function testLoadOneToManyNotLoadedAutomatically(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutationId' => $this->getValidSalutationId(),
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'group' => ['name' => 'test'],
                'addresses' => [
                    array_merge(['id' => $defaultAddressId], $address),
                    $address,
                    $address,
                    $address,
                    $address,
                ],
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        /** @var CustomerEntity $customer */
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertNull($customer->getAddresses());
    }

    public function testLoadOneToMany(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutationId' => $this->getValidSalutationId(),
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'group' => ['name' => 'test'],
                'addresses' => [
                    array_merge(['id' => $defaultAddressId], $address),
                    $address,
                    $address,
                    $address,
                    $address,
                ],
            ],
        ], $context);

        $addresses = $this->connection->fetchColumn('SELECT COUNT(id) FROM customer_address WHERE customer_id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertEquals(5, $addresses);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('addresses');
        /** @var CustomerEntity $customer */
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertInstanceOf(CustomerAddressCollection::class, $customer->getAddresses());
        static::assertCount(5, $customer->getAddresses());
    }

    public function testLoadOneToManySupportsFilter(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $defaultAddressId1 = Uuid::randomHex();
        $defaultAddressId2 = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];
        $customer = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'customerNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => 'A',
            'email' => 'test@test.com',
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'group' => ['name' => 'test'],
        ];

        $repository->upsert([
            array_merge(
                $customer,
                [
                    'id' => $id1,
                    'email' => Uuid::randomHex(),
                    'defaultShippingAddressId' => $defaultAddressId1,
                    'defaultBillingAddressId' => $defaultAddressId1,
                    'addresses' => [
                        array_merge(['id' => $defaultAddressId1], $address),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'X']),
                    ],
                ]
            ),
            array_merge(
                $customer,
                [
                    'id' => $id2,
                    'email' => Uuid::randomHex(),
                    'defaultShippingAddressId' => $defaultAddressId2,
                    'defaultBillingAddressId' => $defaultAddressId2,
                    'addresses' => [
                        array_merge(['id' => $defaultAddressId2], $address),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'C']),
                        array_merge($address, ['zipcode' => 'X']),
                    ],
                ]
            ),
        ], $context);

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];

        $mapping = $this->connection->fetchAll('SELECT * FROM customer WHERE id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(2, $mapping);

        $mapping = $this->connection->fetchAll('SELECT * FROM customer_address WHERE customer_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(8, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('addresses')
            ->addFilter(new EqualsFilter('customer_address.zipcode', 'B'));

        $customers = $repository->search($criteria, $context);

        /** @var CustomerEntity $customer1 */
        $customer1 = $customers->get($id1);
        /** @var CustomerEntity $customer2 */
        $customer2 = $customers->get($id2);

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(2, $customer1->getAddresses());

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(1, $customer2->getAddresses());
    }

    public function testLoadOneToManySupportsSorting(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $addressId1 = Uuid::randomHex();
        $addressId2 = Uuid::randomHex();
        $addressId3 = Uuid::randomHex();
        $addressId4 = Uuid::randomHex();
        $addressId5 = Uuid::randomHex();
        $addressId6 = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];
        $customer = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Test',
            'lastName' => 'Test',
            'customerNumber' => 'A',
            'password' => 'A',
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'group' => ['name' => 'test'],
        ];

        $repository->upsert([
            array_merge(
                $customer,
                [
                    'id' => $id1,
                    'email' => 'test@test.com' . Uuid::randomHex(),
                    'defaultShippingAddressId' => $addressId1,
                    'defaultBillingAddressId' => $addressId1,
                    'addresses' => [
                        array_merge($address, ['id' => $addressId1, 'zipcode' => 'C']),
                        array_merge($address, ['id' => $addressId2, 'zipcode' => 'B']),
                        array_merge($address, ['id' => $addressId3, 'zipcode' => 'X']),
                    ],
                ]
            ),
            array_merge(
                $customer,
                [
                    'id' => $id2,
                    'email' => 'test@test.com' . Uuid::randomHex(),
                    'defaultShippingAddressId' => $addressId4,
                    'defaultBillingAddressId' => $addressId4,
                    'addresses' => [
                        array_merge($address, ['id' => $addressId4, 'zipcode' => 'X']),
                        array_merge($address, ['id' => $addressId5, 'zipcode' => 'B']),
                        array_merge($address, ['id' => $addressId6, 'zipcode' => 'A']),
                    ],
                ]
            ),
        ], $context);

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];

        $mapping = $this->connection->fetchAll('SELECT * FROM customer WHERE id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(2, $mapping);

        $mapping = $this->connection->fetchAll('SELECT * FROM customer_address WHERE customer_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(6, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('addresses')
            ->addSorting(new FieldSorting('customer_address.zipcode', FieldSorting::ASCENDING));

        $customers = $repository->search($criteria, $context);

        /** @var CustomerEntity $customer1 */
        $customer1 = $customers->get($id1);
        /** @var CustomerEntity $customer2 */
        $customer2 = $customers->get($id2);

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(3, $customer1->getAddresses());
        static::assertEquals(
            [$addressId2, $addressId1, $addressId3],
            array_values($customer1->getAddresses()->getIds())
        );

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(3, $customer2->getAddresses());
        static::assertEquals(
            [$addressId6, $addressId5, $addressId4],
            array_values($customer2->getAddresses()->getIds())
        );

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('addresses')
            ->addSorting(new FieldSorting('customer_address.zipcode', FieldSorting::DESCENDING));

        $customers = $repository->search($criteria, $context);

        /** @var CustomerEntity $customer1 */
        $customer1 = $customers->get($id1);
        /** @var CustomerEntity $customer2 */
        $customer2 = $customers->get($id2);

        static::assertEquals(
            [$addressId3, $addressId1, $addressId2],
            array_values($customer1->getAddresses()->getIds())
        );

        static::assertEquals(
            [$addressId4, $addressId5, $addressId6],
            array_values($customer2->getAddresses()->getIds())
        );
    }

    public function testLoadOneToManySupportsSortingAndPagination(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutationId' => $this->getValidSalutationId(),
                'password' => 'A',
                'email' => 'test@test.com' . Uuid::randomHex(),
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'group' => ['name' => 'test'],
                'addresses' => [
                    array_merge(['id' => $defaultAddressId], $address),
                    array_merge($address, ['street' => 'B']),
                    array_merge($address, ['street' => 'X']),
                    array_merge($address, ['street' => 'E']),
                    array_merge($address, ['street' => 'D']),
                ],
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $criteria->getAssociation('addresses')->addSorting(new FieldSorting('street'));

        /** @var CustomerEntity $customer */
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(3, $customer->getAddresses());

        $streets = $customer->getAddresses()->map(function (CustomerAddressEntity $e) {
            return $e->getStreet();
        });
        static::assertEquals(['A', 'B', 'D'], array_values($streets));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $criteria->getAssociation('addresses')->addSorting(new FieldSorting('street', FieldSorting::DESCENDING));

        /** @var CustomerEntity $customer */
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(3, $customer->getAddresses());

        $streets = $customer->getAddresses()->map(function (CustomerAddressEntity $e) {
            return $e->getStreet();
        });
        static::assertEquals(['X', 'E', 'D'], array_values($streets));
    }

    public function testLoadOneToManySupportsPagination(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutationId' => $this->getValidSalutationId(),
                'password' => 'A',
                'email' => 'test@test.com' . Uuid::randomHex(),
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'group' => ['name' => 'test'],
                'addresses' => [
                    array_merge(['id' => $defaultAddressId], $address),
                    $address,
                    $address,
                    $address,
                    $address,
                ],
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(1);

        /** @var CustomerEntity $customer */
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(1, $customer->getAddresses());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $customer = $repository->search($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(3, $customer->getAddresses());
    }

    public function testLoadManyToManyNotLoadedAutomatically(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $product1 = [
            'id' => $id1,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $repository = $this->getContainer()->get('category.repository');
        $context = Context::createDefaultContext();

        $repository->upsert(
            [
                ['id' => $id1, 'stock' => 1, 'name' => 'test', 'products' => [$product1, $product3]],
                ['id' => $id2, 'stock' => 1, 'name' => 'test', 'products' => [$product3, $product2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAll('SELECT * FROM product_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(4, $mapping);

        //test many to many not loaded automatically
        $categories = $repository->search(new Criteria([$id1, $id2]), $context);

        /** @var CategoryEntity $category1 */
        $category1 = $categories->get($id1);
        /** @var CategoryEntity $category2 */
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertNull($category1->getProducts());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertNull($category2->getProducts());
    }

    public function testLoadNestedAssociation(): void
    {
        $manufacturerId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $manufacturer = [
            'id' => $manufacturerId,
            'name' => 'Test manufacturer',
            'products' => [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'test media',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'active' => true,
                    'tax' => ['taxRate' => 13, 'name' => 'green'],
                    'categories' => [
                        [
                            'id' => $categoryId,
                            'name' => 'foobar',
                        ],
                    ],
                ],
            ],
        ];

        $manufacturerRepo = $this->getContainer()->get('product_manufacturer.repository');
        $context = Context::createDefaultContext();
        $manufacturerRepo->upsert([$manufacturer], $context);

        $manufacturerCriteria = new Criteria([$manufacturerId]);

        $manufacturerCriteria->getAssociation('products')
            ->setIds([$productId])
            ->addAssociation('categories');

        /** @var ProductManufacturerEntity $manufacturer */
        $manufacturer = $manufacturerRepo->search($manufacturerCriteria, $context)->get($manufacturerId);
        $products = $manufacturer->getProducts();

        static::assertEquals(1, $products->count());
        static::assertInstanceOf(ProductEntity::class, $products->first());

        $categories = $products->first()->getCategories();
        static::assertEquals(1, $categories->count());
        static::assertInstanceOf(CategoryEntity::class, $categories->first());
    }

    public function testLoadManyToMany(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $product1 = [
            'id' => $id1,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $repository = $this->getContainer()->get('category.repository');
        $context = Context::createDefaultContext();

        $repository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'products' => [$product1, $product3]],
                ['id' => $id2, 'name' => 'test', 'products' => [$product3, $product2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAll('SELECT * FROM product_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(4, $mapping);

        //test that we can add the association and all products are fetched
        $criteria = new Criteria([$id1, $id2]);

        $criteria->addAssociation('products');
        $categories = $repository->search($criteria, $context);

        /** @var CategoryEntity $category1 */
        $category1 = $categories->get($id1);
        /** @var CategoryEntity $category2 */
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(2, $category1->getProducts());

        static::assertContains($id1, $category1->getProducts()->getIds());
        static::assertContains($id3, $category1->getProducts()->getIds());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(2, $category2->getProducts());

        static::assertContains($id2, $category2->getProducts()->getIds());
        static::assertContains($id3, $category2->getProducts()->getIds());
    }

    public function testLoadManyToManySupportsFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $product1 = [
            'id' => $id1,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $repository = $this->getContainer()->get('category.repository');
        $context = Context::createDefaultContext();

        $repository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'products' => [$product1, $product3]],
                ['id' => $id2, 'name' => 'test', 'products' => [$product3, $product2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAll('SELECT * FROM product_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(4, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('products')
            ->addFilter(new EqualsFilter('product.active', true));

        $categories = $repository->search($criteria, $context);

        /** @var CategoryEntity $category1 */
        $category1 = $categories->get($id1);
        /** @var CategoryEntity $category2 */
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(1, $category1->getProducts());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(0, $category2->getProducts());
    }

    public function testLoadManyToManySupportsSorting(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $product1 = [
            'id' => $id1,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'A',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'B',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'C',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $repository = $this->getContainer()->get('category.repository');
        $context = Context::createDefaultContext();

        $repository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'products' => [$product1, $product3]],
                ['id' => $id2, 'name' => 'test', 'products' => [$product3, $product2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAll('SELECT * FROM product_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => Connection::PARAM_STR_ARRAY]);
        static::assertCount(4, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('products')
            ->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));

        $categories = $repository->search($criteria, $context);

        /** @var CategoryEntity $category1 */
        $category1 = $categories->get($id1);
        /** @var CategoryEntity $category2 */
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(2, $category1->getProducts());

        static::assertEquals(
            [$id1, $id3],
            array_values($category1->getProducts()->getIds())
        );

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(2, $category2->getProducts());

        static::assertEquals(
            [$id2, $id3],
            array_values($category2->getProducts()->getIds())
        );

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('products')
            ->addSorting(new FieldSorting('product.name', FieldSorting::DESCENDING));
        $categories = $repository->search($criteria, $context);

        /** @var CategoryEntity $category1 */
        $category1 = $categories->get($id1);
        /** @var CategoryEntity $category2 */
        $category2 = $categories->get($id2);

        static::assertEquals(
            [$id3, $id1],
            array_values($category1->getProducts()->getIds())
        );

        static::assertEquals(
            [$id3, $id2],
            array_values($category2->getProducts()->getIds())
        );
    }

    public function testLoadManyToManySupportsPagination(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $products = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'categories' => [
                    ['name' => 'test1'],
                    ['name' => 'test2'],
                    ['name' => 'test3'],
                    ['name' => 'test4'],
                    ['name' => 'test5'],
                    ['name' => 'test6'],
                    ['name' => 'test7'],
                    ['name' => 'test8'],
                    ['name' => 'test9'],
                ],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'categories' => [
                    ['name' => 'test10'],
                    ['name' => 'test11'],
                    ['name' => 'test12'],
                    ['name' => 'test13'],
                    ['name' => 'test14'],
                    ['name' => 'test15'],
                    ['name' => 'test16'],
                    ['name' => 'test17'],
                    ['name' => 'test18'],
                ],
            ],
        ];

        $this->productRepository->upsert($products, $context);

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('categories')->setLimit(3);

        $products = $this->productRepository->search($criteria, $context);

        static::assertCount(2, $products);

        /** @var ProductEntity $product1 */
        $product1 = $products->get($id1);
        /** @var ProductEntity $product2 */
        $product2 = $products->get($id2);

        static::assertInstanceOf(CategoryCollection::class, $product1->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $product2->getCategories());

        static::assertCount(3, $product1->getCategories());
        static::assertCount(3, $product2->getCategories());
    }

    public function testReadSupportsConditions(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $products = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->upsert($products, $context);

        $criteria = new Criteria([$id1, $id2]);

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(2, $products);

        $criteria->addFilter(new EqualsFilter('product.active', true));
        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);
    }

    public function testReadRelationWithNestedToManyRelations(): void
    {
        $context = Context::createDefaultContext();

        $data = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'cover' => [
                'position' => 1,
                'media' => [
                    'name' => 'test-image',
                    'thumbnails' => [
                        ['id' => Uuid::randomHex(), 'width' => 10, 'height' => 10, 'highDpi' => true],
                        ['id' => Uuid::randomHex(), 'width' => 20, 'height' => 20, 'highDpi' => true],
                        ['id' => Uuid::randomHex(), 'width' => 30, 'height' => 30, 'highDpi' => true],
                    ],
                ],
            ],
        ];

        $this->productRepository->create([$data], $context);
        $criteria = new Criteria([$data['id']]);
        $criteria->addAssociation('cover');
        $results = $this->productRepository->search($criteria, $context);

        /** @var ProductEntity $product */
        $product = $results->first();

        static::assertNotNull($product, 'Product has not been created.');
        static::assertNotNull($product->getCover(), 'Cover was not fetched.');
        static::assertNotNull($product->getCover()->getMedia(), 'Media for cover was not fetched.');
        static::assertCount(3, $product->getCover()->getMedia()->getThumbnails()->getElements(), 'Thumbnails were not fetched or is incomplete.');
    }

    public function testAddTranslationsAssociation(): void
    {
        $repo = $this->getContainer()->get('category.repository');

        $id = Uuid::randomHex();

        $cats = [
            [
                'id' => $id,
                'name' => 'system',
                'translations' => [
                    'de-DE' => [
                        'name' => 'deutsch',
                    ],
                ],
            ],
        ];

        $repo->create($cats, Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('translations');

        /** @var CategoryEntity $cat */
        $cat = $repo->search($criteria, Context::createDefaultContext())->first();
        static::assertCount(2, $cat->getTranslations());

        /** @var CategoryTranslationEntity $transDe */
        $transDe = $cat->getTranslations()->filterByLanguageId($this->deLanguageId)->first();
        static::assertEquals('deutsch', $transDe->getName());

        /** @var CategoryTranslationEntity $transSystem */
        $transSystem = $cat->getTranslations()->filterByLanguageId(Defaults::LANGUAGE_SYSTEM)->first();
        static::assertEquals('system', $transSystem->getName());
    }

    public function testPricesAreConvertedWithCurrencyFactor(): void
    {
        $productId = Uuid::randomHex();

        $product = [
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 7, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'test',
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8, 'net' => 6, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'test',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($product, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price', 8.0));

        $searchContext = new Context(
            new SystemSource(),
            $context->getRuleIds(),
            Uuid::randomHex(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            0.8
        );

        $products = $this->productRepository->search($criteria, $searchContext);
        static::assertSame(1, $products->getTotal());

        /** @var ProductEntity $product */
        $product = $products->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);
    }
}
