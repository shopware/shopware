<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Reader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerStruct;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxStruct;

class EntityReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EntityRepository
     */
    private $languageRepository;

    protected function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        parent::setUp();
    }

    public function testTransledFieldsContainsNoInheritance()
    {
        $id = Uuid::uuid4()->getHex();

        $subLanguageId = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_EN,
                'localeId' => Defaults::LOCALE_EN_GB,
            ],
        ], $context);

        $product = [
            'id' => $id,
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_EN => ['name' => 'EN'],
                Defaults::LANGUAGE_DE => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $context = new Context(
            $context->getSourceContext(),
            $context->getCatalogIds(),
            $context->getRules(),
            $context->getCurrencyId(),
            $subLanguageId,
            Defaults::LANGUAGE_EN
        );

        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();

        /** @var ProductStruct $product */
        static::assertNull($product->getName());
        static::assertEquals('test', $product->getDescription());

        static::assertInstanceOf(ProductTranslationCollection::class, $product->getTranslations());
        static::assertCount(2, $product->getTranslations());

        $translation = $product->getTranslations()->get($id . '-' . $subLanguageId);
        static::assertInstanceOf(ProductTranslationStruct::class, $translation);
        static::assertEquals('test', $translation->getDescription());
        static::assertNull($translation->getName());

        $translation = $product->getTranslations()->get($id . '-' . Defaults::LANGUAGE_EN);
        static::assertInstanceOf(ProductTranslationStruct::class, $translation);
        static::assertEquals('EN', $translation->getName());
        static::assertNull($translation->getDescription());
    }

    public function testInheritedTranslationsInViewData()
    {
        $id = Uuid::uuid4()->getHex();

        $subLanguageId = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_EN,
                'localeId' => Defaults::LOCALE_EN_GB,
            ],
        ], $context);

        $product = [
            'id' => $id,
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_EN => ['name' => 'EN'],
                Defaults::LANGUAGE_DE => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $context = new Context(
            $context->getSourceContext(),
            $context->getCatalogIds(),
            $context->getRules(),
            $context->getCurrencyId(),
            $subLanguageId,
            Defaults::LANGUAGE_EN
        );

        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();

        /** @var ProductStruct $product */
        static::assertInstanceOf(ProductStruct::class, $product->getViewData());
        static::assertEquals('EN', $product->getViewData()->getName());
        static::assertEquals('test', $product->getViewData()->getDescription());
    }

    public function testParentInheritanceInViewData()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentTax = Uuid::uuid4()->getHex();
        $greenTax = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'price' => ['gross' => 50, 'net' => 50, 'linked' => true],
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'parent tax'],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'price' => ['gross' => 100, 'net' => 100, 'linked' => true],
                'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green tax'],
            ],
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $products = $this->productRepository->read(new ReadCriteria([$parentId, $greenId, $redId]), Context::createDefaultContext());

        /** @var ProductStruct $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductStruct::class, $parent);
        static::assertInstanceOf(ProductStruct::class, $parent->getViewData());

        static::assertInstanceOf(TaxStruct::class, $parent->getTax());
        static::assertInstanceOf(TaxStruct::class, $parent->getViewData()->getTax());

        static::assertInstanceOf(Price::class, $parent->getPrice());
        static::assertInstanceOf(Price::class, $parent->getViewData()->getPrice());

        static::assertEquals(50, $parent->getPrice()->getGross());
        static::assertEquals(50, $parent->getViewData()->getPrice()->getGross());

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        //check red product contains full inheritance of parent in "viewData"
        static::assertInstanceOf(ProductStruct::class, $red);

        //has no own tax
        static::assertNull($red->getTax());
        static::assertNull($red->getTaxId());
        static::assertNull($red->getPrice());

        //price and tax are inherited by parent
        static::assertInstanceOf(Price::class, $red->getViewData()->getPrice());
        static::assertInstanceOf(TaxStruct::class, $red->getViewData()->getTax());
        static::assertEquals($parentTax, $red->getViewData()->getTaxId());
        static::assertInstanceOf(Price::class, $red->getViewData()->getPrice());
        static::assertEquals(50, $red->getViewData()->getPrice()->getGross());

        /** @var ProductStruct $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductStruct::class, $green);
        static::assertInstanceOf(ProductStruct::class, $green->getViewData());

        static::assertInstanceOf(TaxStruct::class, $green->getTax());
        static::assertInstanceOf(TaxStruct::class, $green->getViewData()->getTax());

        static::assertEquals($greenTax, $green->getTaxId());
        static::assertEquals($greenTax, $green->getViewData()->getTaxId());

        static::assertInstanceOf(Price::class, $green->getPrice());
        static::assertInstanceOf(Price::class, $green->getViewData()->getPrice());

        static::assertEquals(100, $green->getPrice()->getGross());
        static::assertEquals(100, $green->getViewData()->getPrice()->getGross());
    }

    public function testInheritanceWithOneToMany()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 100, 'net' => 100],
                    ],
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 50],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 50],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);

        $products = $this->productRepository->read(new ReadCriteria([$greenId, $parentId, $redId]), $context);

        /** @var ProductStruct $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductStruct::class, $parent);
        static::assertInstanceOf(ProductPriceRuleCollection::class, $parent->getPriceRules());

        /** @var ProductStruct $red */
        $red = $products->get($redId);
        static::assertInstanceOf(ProductStruct::class, $red);
        static::assertCount(0, $red->getPriceRules());
        static::assertInstanceOf(ProductPriceRuleCollection::class, $red->getViewData()->getPriceRules());
        static::assertCount(2, $red->getViewData()->getPriceRules());

        /** @var ProductStruct $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductStruct::class, $green);
        static::assertInstanceOf(ProductPriceRuleCollection::class, $green->getPriceRules());
    }

    public function testInheritanceWithPaginatedOneToMany()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 100, 'net' => 100],
                    ],
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 50],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 50],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create($products, $context);

        $criteria = new ReadCriteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('product.priceRules', new PaginationCriteria(5));

        $products = $this->productRepository->read($criteria, $context);

        /** @var ProductStruct $parent */
        $parent = $products->get($parentId);
        static::assertInstanceOf(ProductStruct::class, $parent);
        static::assertInstanceOf(ProductPriceRuleCollection::class, $parent->getPriceRules());

        /** @var ProductStruct $red */
        $red = $products->get($redId);
        static::assertInstanceOf(ProductStruct::class, $red);
        static::assertCount(0, $red->getPriceRules());
        static::assertInstanceOf(ProductPriceRuleCollection::class, $red->getViewData()->getPriceRules());
        static::assertCount(2, $red->getViewData()->getPriceRules());

        /** @var ProductStruct $green */
        $green = $products->get($greenId);
        static::assertInstanceOf(ProductStruct::class, $green);
        static::assertInstanceOf(ProductPriceRuleCollection::class, $green->getPriceRules());
    }

    public function testInheritanceWithManyToMany()
    {
        $category1 = Uuid::uuid4()->getHex();
        $category2 = Uuid::uuid4()->getHex();
        $category3 = Uuid::uuid4()->getHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
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
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new ReadCriteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('product.categories');

        $products = $this->productRepository->read($criteria, $context);

        $parent = $products->get($parentId);
        $red = $products->get($redId);
        $green = $products->get($greenId);

        /** @var ProductStruct $parent */
        static::assertInstanceOf(ProductStruct::class, $parent);
        /** @var ProductStruct $red */
        static::assertInstanceOf(ProductStruct::class, $red);
        /** @var ProductStruct $green */
        static::assertInstanceOf(ProductStruct::class, $green);

        //validate parent contains own categories
        static::assertCount(2, $parent->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate parent view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $parent->getViewData()->getCategories());
        static::assertCount(2, $parent->getViewData()->getCategories());
        static::assertTrue($parent->getViewData()->getCategories()->has($category1));
        static::assertTrue($parent->getViewData()->getCategories()->has($category3));

        //validate red contains no own categories
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertCount(0, $red->getCategories());

        //validate red view data contains the categories of the parent
        static::assertCount(2, $red->getViewData()->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $red->getViewData()->getCategories());
        static::assertTrue($red->getViewData()->getCategories()->has($category1));
        static::assertTrue($red->getViewData()->getCategories()->has($category3));

        //validate green contains own categories
        static::assertCount(1, $green->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        //validate green view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $green->getViewData()->getCategories());
        static::assertCount(1, $green->getViewData()->getCategories());
        static::assertTrue($green->getViewData()->getCategories()->has($category2));
    }

    public function testInheritanceWithPaginatedManyToMany()
    {
        $category1 = Uuid::uuid4()->getHex();
        $category2 = Uuid::uuid4()->getHex();
        $category3 = Uuid::uuid4()->getHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
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
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new ReadCriteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('product.categories', new PaginationCriteria(3));

        $products = $this->productRepository->read($criteria, $context);

        $parent = $products->get($parentId);
        $red = $products->get($redId);
        $green = $products->get($greenId);

        /** @var ProductStruct $parent */
        static::assertInstanceOf(ProductStruct::class, $parent);
        /** @var ProductStruct $red */
        static::assertInstanceOf(ProductStruct::class, $red);
        /** @var ProductStruct $green */
        static::assertInstanceOf(ProductStruct::class, $green);

        //validate parent contains own categories
        static::assertCount(2, $parent->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $parent->getCategories());
        static::assertTrue($parent->getCategories()->has($category1));
        static::assertTrue($parent->getCategories()->has($category3));

        //validate parent view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $parent->getViewData()->getCategories());
        static::assertCount(2, $parent->getViewData()->getCategories());
        static::assertTrue($parent->getViewData()->getCategories()->has($category1));
        static::assertTrue($parent->getViewData()->getCategories()->has($category3));

        //validate red contains no own categories
        static::assertInstanceOf(CategoryCollection::class, $red->getCategories());
        static::assertCount(0, $red->getCategories());

        //validate red view data contains the categories of the parent
        static::assertCount(2, $red->getViewData()->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $red->getViewData()->getCategories());
        static::assertTrue($red->getViewData()->getCategories()->has($category1));
        static::assertTrue($red->getViewData()->getCategories()->has($category3));

        //validate green contains own categories
        static::assertCount(1, $green->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        //validate green view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $green->getViewData()->getCategories());
        static::assertCount(1, $green->getViewData()->getCategories());
        static::assertTrue($green->getViewData()->getCategories()->has($category2));
    }

    public function testLoadOneToManyNotLoadedAutomatically(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutation' => 'A',
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => Defaults::COUNTRY,
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
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

        /** @var CustomerStruct $customer */
        $criteria = new ReadCriteria([$id]);
        $customer = $repository->read($criteria, $context)->get($id);
        static::assertNull($customer->getAddresses());
    }

    public function testLoadOneToMany(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutation' => 'A',
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => Defaults::COUNTRY,
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
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

        /** @var CustomerStruct $customer */
        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('customer.addresses');
        $customer = $repository->read($criteria, $context)->get($id);
        static::assertInstanceOf(CustomerAddressCollection::class, $customer->getAddresses());
        static::assertCount(5, $customer->getAddresses());
    }

    public function testLoadOneToManySupportsFilter(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $defaultAddressId1 = Uuid::uuid4()->getHex();
        $defaultAddressId2 = Uuid::uuid4()->getHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutation' => 'A',
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => Defaults::COUNTRY,
        ];
        $customer = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'customerNumber' => 'A',
            'salutation' => 'A',
            'password' => 'A',
            'email' => 'test@test.com',
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'group' => ['name' => 'test'],
        ];

        $repository->upsert([
            array_merge(
                $customer,
                [
                    'id' => $id1,
                    'email' => Uuid::uuid4()->getHex(),
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
                    'email' => Uuid::uuid4()->getHex(),
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

        /** @var CustomerStruct $customer1 */
        /** @var CustomerStruct $customer2 */
        $criteria = new ReadCriteria([$id1, $id2]);
        $addressCriteria = new Criteria();
        $addressCriteria->addFilter(new EqualsFilter('customer_address.zipcode', 'B'));
        $criteria->addAssociation('customer.addresses', $addressCriteria);

        $customers = $repository->read($criteria, $context);

        $customer1 = $customers->get($id1);
        $customer2 = $customers->get($id2);

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(2, $customer1->getAddresses());

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(1, $customer2->getAddresses());
    }

    public function testLoadOneToManySupportsSorting(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $addressId1 = Uuid::uuid4()->getHex();
        $addressId2 = Uuid::uuid4()->getHex();
        $addressId3 = Uuid::uuid4()->getHex();
        $addressId4 = Uuid::uuid4()->getHex();
        $addressId5 = Uuid::uuid4()->getHex();
        $addressId6 = Uuid::uuid4()->getHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutation' => 'A',
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => Defaults::COUNTRY,
        ];
        $customer = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'customerNumber' => 'A',
            'salutation' => 'A',
            'password' => 'A',
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'group' => ['name' => 'test'],
        ];

        $repository->upsert([
            array_merge(
                $customer,
                [
                    'id' => $id1,
                    'email' => 'test@test.com' . Uuid::uuid4()->getHex(),
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
                    'email' => 'test@test.com' . Uuid::uuid4()->getHex(),
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

        /** @var CustomerStruct $customer1 */
        /** @var CustomerStruct $customer2 */
        $criteria = new ReadCriteria([$id1, $id2]);
        $addressCriteria = new Criteria();
        $addressCriteria->addSorting(new FieldSorting('customer_address.zipcode', FieldSorting::ASCENDING));
        $criteria->addAssociation('customer.addresses', $addressCriteria);

        $customers = $repository->read($criteria, $context);

        $customer1 = $customers->get($id1);
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

        /** @var CustomerStruct $customer1 */
        /** @var CustomerStruct $customer2 */
        $criteria = new ReadCriteria([$id1, $id2]);
        $addressCriteria = new Criteria();
        $addressCriteria->addSorting(new FieldSorting('customer_address.zipcode', FieldSorting::DESCENDING));
        $criteria->addAssociation('customer.addresses', $addressCriteria);

        $customers = $repository->read($criteria, $context);

        $customer1 = $customers->get($id1);
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

    public function testLoadOneToManySupportsPagination(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = $this->getContainer()->get('customer.repository');

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutation' => 'A',
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => Defaults::COUNTRY,
        ];

        $repository->upsert([
            [
                'id' => $id,
                'firstName' => 'Test',
                'lastName' => 'Test',
                'customerNumber' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . Uuid::uuid4()->getHex(),
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
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

        /** @var CustomerStruct $customer */
        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('customer.addresses', new PaginationCriteria(1));
        $customer = $repository->read($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(1, $customer->getAddresses());

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('customer.addresses', new PaginationCriteria(3));
        $customer = $repository->read($criteria, $context)->get($id);
        static::assertNotNull($customer->getAddresses());
        static::assertCount(3, $customer->getAddresses());
    }

    public function testLoadManyToManyNotLoadedAutomatically(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $product1 = [
            'id' => $id1,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'price' => ['gross' => 10, 'net' => 9],
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

        //test many to many not loaded automatically
        $categories = $repository->read(new ReadCriteria([$id1, $id2]), $context);

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryStruct::class, $category1);
        /* @var CategoryStruct $category1 */
        static::assertNull($category1->getProducts());

        static::assertInstanceOf(CategoryStruct::class, $category2);
        /* @var CategoryStruct $category2 */
        static::assertNull($category2->getProducts());
    }

    public function testLoadNestedAssociation(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $productId = Uuid::uuid4()->getHex();
        $categoryId = Uuid::uuid4()->getHex();

        $manufacturer = [
            'id' => $manufacturerId,
            'name' => 'Test manufacturer',
            'products' => [
                [
                    'id' => $productId,
                    'name' => 'test media',
                    'price' => ['gross' => 10, 'net' => 9],
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

        $productCriteria = new ReadCriteria([$productId]);
        $productCriteria->addAssociation('product.categories');

        $manufacturerCriteria = new ReadCriteria([$manufacturerId]);
        $manufacturerCriteria->addAssociation('product_manufacturer.products', $productCriteria);

        /** @var ProductManufacturerStruct $manufacturer */
        $manufacturer = $manufacturerRepo->read($manufacturerCriteria, $context)->get($manufacturerId);
        $products = $manufacturer->getProducts();

        static::assertEquals(1, $products->count());
        static::assertInstanceOf(ProductStruct::class, $products->first());

        $categories = $products->first()->getCategories();
        static::assertEquals(1, $categories->count());
        static::assertInstanceOf(CategoryStruct::class, $categories->first());
    }

    public function testLoadManyToMany(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $product1 = [
            'id' => $id1,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'price' => ['gross' => 10, 'net' => 9],
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
        $criteria = new ReadCriteria([$id1, $id2]);

        $criteria->addAssociation('category.products');
        $categories = $repository->read($criteria, $context);

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryStruct::class, $category1);
        /* @var CategoryStruct $category1 */
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(2, $category1->getProducts());

        static::assertContains($id1, $category1->getProducts()->getIds());
        static::assertContains($id3, $category1->getProducts()->getIds());

        static::assertInstanceOf(CategoryStruct::class, $category2);
        /* @var CategoryStruct $category2 */
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(2, $category2->getProducts());

        static::assertContains($id2, $category2->getProducts()->getIds());
        static::assertContains($id3, $category2->getProducts()->getIds());
    }

    public function testLoadManyToManySupportsFilter(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $product1 = [
            'id' => $id1,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'price' => ['gross' => 10, 'net' => 9],
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

        $criteria = new ReadCriteria([$id1, $id2]);

        $productCriteria = new Criteria();
        $productCriteria->addFilter(new EqualsFilter('product.active', true));

        $criteria->addAssociation('category.products', $productCriteria);
        $categories = $repository->read($criteria, $context);

        /** @var CategoryStruct $category1 */
        /** @var CategoryStruct $category2 */
        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryStruct::class, $category1);
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(1, $category1->getProducts());

        static::assertInstanceOf(CategoryStruct::class, $category2);
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(0, $category2->getProducts());
    }

    public function testLoadManyToManySupportsSorting(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $product1 = [
            'id' => $id1,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'A',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product2 = [
            'id' => $id2,
            'price' => ['gross' => 10, 'net' => 9],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'B',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $product3 = [
            'id' => $id3,
            'price' => ['gross' => 10, 'net' => 9],
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

        $criteria = new ReadCriteria([$id1, $id2]);

        $productCriteria = new Criteria();
        $productCriteria->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));

        $criteria->addAssociation('category.products', $productCriteria);
        $categories = $repository->read($criteria, $context);

        /** @var CategoryStruct $category1 */
        /** @var CategoryStruct $category2 */
        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryStruct::class, $category1);
        static::assertInstanceOf(ProductCollection::class, $category1->getProducts());
        static::assertCount(2, $category1->getProducts());

        static::assertEquals(
            [$id1, $id3],
            array_values($category1->getProducts()->getIds())
        );

        static::assertInstanceOf(CategoryStruct::class, $category2);
        static::assertInstanceOf(ProductCollection::class, $category2->getProducts());
        static::assertCount(2, $category2->getProducts());

        static::assertEquals(
            [$id2, $id3],
            array_values($category2->getProducts()->getIds())
        );

        $criteria = new ReadCriteria([$id1, $id2]);

        $productCriteria = new Criteria();
        $productCriteria->addSorting(new FieldSorting('product.name', FieldSorting::DESCENDING));

        $criteria->addAssociation('category.products', $productCriteria);
        $categories = $repository->read($criteria, $context);

        /** @var CategoryStruct $category1 */
        /** @var CategoryStruct $category2 */
        $category1 = $categories->get($id1);
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
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $products = [
            [
                'id' => $id1,
                'price' => ['gross' => 10, 'net' => 9],
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
                'price' => ['gross' => 10, 'net' => 9],
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

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addAssociation('product.categories', new PaginationCriteria(3));

        $products = $this->productRepository->read($criteria, $context);

        static::assertCount(2, $products);

        /** @var ProductStruct $product1 */
        /** @var ProductStruct $product2 */
        $product1 = $products->get($id1);
        $product2 = $products->get($id2);

        static::assertInstanceOf(CategoryCollection::class, $product1->getCategories());
        static::assertInstanceOf(CategoryCollection::class, $product2->getCategories());

        static::assertCount(3, $product1->getCategories());
        static::assertCount(3, $product2->getCategories());
    }

    public function testReadSupportsConditions(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $id1,
                'price' => ['gross' => 10, 'net' => 9],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $id2,
                'price' => ['gross' => 10, 'net' => 9],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->upsert($products, $context);

        $criteria = new ReadCriteria([$id1, $id2]);

        $products = $this->productRepository->read($criteria, $context);
        static::assertCount(2, $products);

        $criteria->addFilter(new EqualsFilter('product.active', true));
        $products = $this->productRepository->read($criteria, $context);
        static::assertCount(1, $products);
    }

    public function testReadNotSupportsSorting(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $id1,
                'price' => ['gross' => 10, 'net' => 9],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'B',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $id2,
                'price' => ['gross' => 10, 'net' => 9],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'A',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->upsert($products, $context);

        $criteria = new ReadCriteria([$id1, $id2]);

        $products = $this->productRepository->read($criteria, $context);
        static::assertCount(2, $products);

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));
        $products = $this->productRepository->read($criteria, $context);

        static::assertEquals(
            [$id1, $id2],
            array_values($products->getIds())
        );

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addSorting(new FieldSorting('product.name', FieldSorting::DESCENDING));
        $products = $this->productRepository->read($criteria, $context);

        static::assertEquals(
            [$id1, $id2],
            array_values($products->getIds())
        );
    }

    public function testReadRelationWithNestedToManyRelations(): void
    {
        $context = Context::createDefaultContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO, MediaProtectionFlags::WRITE_THUMBNAILS);

        $data = [
            'id' => Uuid::uuid4()->getHex(),
            'price' => ['gross' => 10, 'net' => 9],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'cover' => [
                'position' => 1,
                'media' => [
                    'name' => 'test-image',
                    'thumbnails' => [
                        ['id' => Uuid::uuid4()->getHex(), 'width' => 10, 'height' => 10, 'highDpi' => true],
                        ['id' => Uuid::uuid4()->getHex(), 'width' => 20, 'height' => 20, 'highDpi' => true],
                        ['id' => Uuid::uuid4()->getHex(), 'width' => 30, 'height' => 30, 'highDpi' => true],
                    ],
                ],
            ],
        ];

        $this->productRepository->create([$data], $context);
        $results = $this->productRepository->read(new ReadCriteria([$data['id']]), $context);

        /** @var ProductStruct $product */
        $product = $results->first();

        static::assertNotNull($product, 'Product has not been created.');
        static::assertNotNull($product->getCover(), 'Cover was not fetched.');
        static::assertNotNull($product->getCover()->getMedia(), 'Media for cover was not fetched.');
        static::assertCount(3, $product->getCover()->getMedia()->getThumbnails()->getElements(), 'Thumbnails were not fetched or is incomplete.');
    }
}
