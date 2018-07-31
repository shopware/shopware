<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\PaginationCriteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntityReaderTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();

        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();

        $this->repository = self::$container->get('product.repository');
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testInheritanceExtension()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentTax = Uuid::uuid4()->getHex();
        $greenTax = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green'],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$redId, $greenId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        static::assertTrue($red->hasExtension('inherited'));

        /** @var ArrayStruct $inheritance */
        $inheritance = $red->getExtension('inherited');

        static::assertTrue($inheritance->get('manufacturerId'));
        static::assertTrue($inheritance->get('unitId'));
        static::assertTrue($inheritance->get('taxId'));

        /** @var ProductStruct $green */
        $green = $products->get($greenId);
        $inheritance = $green->getExtension('inherited');
        static::assertFalse($inheritance->get('taxId'));
    }

    public function testInheritanceExtensionWithAssociation()
    {
        $ruleA = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            [
                'id' => $ruleA,
                'name' => 'test',
                'payload' => new AndRule(),
                'priority' => 1,
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $parentId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => $parentId,
                'name' => 'price test',
                'price' => ['gross' => 15, 'net' => 10],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 15, 'net' => 10],
                    ],
                ],
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 100, 'net' => 90],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$redId, $greenId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        static::assertTrue($red->hasExtension('inherited'));

        /** @var ArrayStruct $inheritance */
        $inheritance = $red->getExtension('inherited');

        static::assertTrue($inheritance->get('manufacturerId'));
        static::assertTrue($inheritance->get('unitId'));
        static::assertTrue($inheritance->get('priceRules'));

        /** @var ProductStruct $green */
        $green = $products->get($greenId);
        $inheritance = $green->getExtension('inherited');
        static::assertFalse($inheritance->get('priceRules'));
    }

    public function testTranslationExtension()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();
        $parentTax = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$redId, $greenId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        /* @var ArrayStruct $translated */
        /* @var ArrayStruct $inheritance */
        static::assertTrue($red->hasExtension('translated'));
        static::assertTrue($red->hasExtension('inherited'));

        $inheritance = $red->getExtension('inherited');
        $translated = $red->getExtension('translated');

        static::assertTrue($translated->get('name'));
        static::assertFalse($inheritance->get('name'));

        static::assertFalse($translated->get('description'));
        static::assertTrue($inheritance->get('description'));

        /** @var ProductStruct $green */
        $green = $products->get($greenId);

        static::assertTrue($green->hasExtension('translated'));
        static::assertTrue($green->hasExtension('inherited'));

        $inheritance = $green->getExtension('inherited');
        $translated = $green->getExtension('translated');

        static::assertTrue($translated->get('name'));
        static::assertTrue($inheritance->get('name'));

        static::assertFalse($translated->get('description'));
        static::assertTrue($inheritance->get('description'));
    }

    public function testLoadOneToManyNotLoadedAutomatically()
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = self::$container->get('customer.repository');

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
                'number' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'touchpointId' => Defaults::TOUCHPOINT,
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

    public function testLoadOneToMany()
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = self::$container->get('customer.repository');

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
                'number' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . $id,
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'touchpointId' => Defaults::TOUCHPOINT,
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

    public function testLoadOneToManySupportsFilter()
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $defaultAddressId1 = Uuid::uuid4()->getHex();
        $defaultAddressId2 = Uuid::uuid4()->getHex();

        $repository = self::$container->get('customer.repository');

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
            'number' => 'A',
            'salutation' => 'A',
            'password' => 'A',
            'email' => 'test@test.com',
            'touchpointId' => Defaults::TOUCHPOINT,
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
        $addressCriteria->addFilter(new TermQuery('customer_address.zipcode', 'B'));
        $criteria->addAssociation('customer.addresses', $addressCriteria);

        $customers = $repository->read($criteria, $context);

        $customer1 = $customers->get($id1);
        $customer2 = $customers->get($id2);

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(2, $customer1->getAddresses());

        static::assertInstanceOf(CustomerAddressCollection::class, $customer1->getAddresses());
        static::assertCount(1, $customer2->getAddresses());
    }

    public function testLoadOneToManySupportsSorting()
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $addressId1 = Uuid::uuid4()->getHex();
        $addressId2 = Uuid::uuid4()->getHex();
        $addressId3 = Uuid::uuid4()->getHex();
        $addressId4 = Uuid::uuid4()->getHex();
        $addressId5 = Uuid::uuid4()->getHex();
        $addressId6 = Uuid::uuid4()->getHex();

        $repository = self::$container->get('customer.repository');

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
            'number' => 'A',
            'salutation' => 'A',
            'password' => 'A',
            'touchpointId' => Defaults::TOUCHPOINT,
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

    public function testLoadOneToManySupportsPagination()
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();
        $defaultAddressId = Uuid::uuid4()->getHex();

        $repository = self::$container->get('customer.repository');

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
                'number' => 'A',
                'salutation' => 'A',
                'password' => 'A',
                'email' => 'test@test.com' . Uuid::uuid4()->getHex(),
                'defaultShippingAddressId' => $defaultAddressId,
                'defaultBillingAddressId' => $defaultAddressId,
                'touchpointId' => Defaults::TOUCHPOINT,
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

    public function testLoadManyToManyNotLoadedAutomatically()
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

        $repository = self::$container->get('category.repository');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

    public function testLoadManyToMany()
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

        $repository = self::$container->get('category.repository');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

    public function testLoadManyToManySupportsFilter()
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

        $repository = self::$container->get('category.repository');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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
        $productCriteria->addFilter(new TermQuery('product.active', true));

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

    public function testLoadManyToManySupportsSorting()
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

        $repository = self::$container->get('category.repository');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

    public function testLoadManyToManySupportsPagination()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $this->repository->upsert($products, $context);

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addAssociation('product.categories', new PaginationCriteria(3));

        $products = $this->repository->read($criteria, $context);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->repository->upsert($products, $context);

        $criteria = new ReadCriteria([$id1, $id2]);

        $products = $this->repository->read($criteria, $context);
        static::assertCount(2, $products);

        $criteria->addFilter(new TermQuery('product.active', true));
        $products = $this->repository->read($criteria, $context);
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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->repository->upsert($products, $context);

        $criteria = new ReadCriteria([$id1, $id2]);

        $products = $this->repository->read($criteria, $context);
        static::assertCount(2, $products);

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));
        $products = $this->repository->read($criteria, $context);

        static::assertEquals(
            [$id1, $id2],
            array_values($products->getIds())
        );

        $criteria = new ReadCriteria([$id1, $id2]);
        $criteria->addSorting(new FieldSorting('product.name', FieldSorting::DESCENDING));
        $products = $this->repository->read($criteria, $context);

        static::assertEquals(
            [$id1, $id2],
            array_values($products->getIds())
        );
    }
}
