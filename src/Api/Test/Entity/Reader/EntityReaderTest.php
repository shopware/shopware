<?php declare(strict_types=1);

namespace Shopware\Api\Test\Entity\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Context\Repository\ContextRuleRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\ArrayStruct;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityReaderTest extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductRepository
     */
    private $repository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->container = self::$kernel->getContainer();

        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');

        $this->repository = $this->container->get(ProductRepository::class);
    }

    public function testMaxGroupConcat()
    {
        $parentId = Uuid::uuid4()->getHex();
        $categories = [
            ['id' => $parentId, 'name' => 'master'],
        ];

        for ($i = 0; $i < 400; ++$i) {
            $categories[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test' . $i,
                'parentId' => $parentId,
            ];
        }

        $this->container->get(CategoryRepository::class)
            ->create($categories, ShopContext::createDefaultContext());

        $mapping = array_map(function (array $category) {
            return ['id' => $category['id']];
        }, $categories);

        $id = Uuid::uuid4()->getHex();
        $product = [
            'id' => $id,
            'name' => 'Test product',
            'price' => ['gross' => 100, 'net' => 99],
            'categories' => $mapping,
            'manufacturer' => ['name' => 'Test'],
            'tax' => ['name' => 'test', 'rate' => 5],
        ];

        $this->container->get(ProductRepository::class)
            ->create([$product], ShopContext::createDefaultContext());

        $detail = $this->container->get(ProductRepository::class)
            ->readDetail([$id], ShopContext::createDefaultContext());

        $this->assertCount(401, $detail->getAllCategories());
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
                'tax' => ['id' => $parentTax, 'rate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'tax' => ['id' => $greenTax, 'rate' => 13, 'name' => 'green'],
            ],
        ];

        $this->repository->create($products, ShopContext::createDefaultContext());

        $products = $this->repository->readBasic([$redId, $greenId], ShopContext::createDefaultContext());

        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductBasicStruct $red */
        $red = $products->get($redId);

        $this->assertTrue($red->hasExtension('inherited'));

        /** @var ArrayStruct $inheritance */
        $inheritance = $red->getExtension('inherited');

        $this->assertTrue($inheritance->get('manufacturerId'));
        $this->assertTrue($inheritance->get('unitId'));
        $this->assertTrue($inheritance->get('taxId'));

        /** @var ProductBasicStruct $green */
        $green = $products->get($greenId);
        $inheritance = $green->getExtension('inherited');
        $this->assertFalse($inheritance->get('taxId'));
    }

    public function testInheritanceExtensionWithAssociation()
    {
        $ruleA = Uuid::uuid4()->getHex();

        $this->container->get(ContextRuleRepository::class)->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule()],
        ], ShopContext::createDefaultContext());

        $parentId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => $parentId,
                'name' => 'price test',
                'price' => ['gross' => 15, 'net' => 10],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'contextPrices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'contextRuleId' => $ruleA,
                        'price' => ['gross' => 15, 'net' => 10],
                    ],
                ],
            ],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'contextPrices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'contextRuleId' => $ruleA,
                        'price' => ['gross' => 100, 'net' => 90],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'parentId' => $parentId,
            ],
        ];

        $this->repository->create($data, ShopContext::createDefaultContext());

        $products = $this->repository->readBasic([$redId, $greenId], ShopContext::createDefaultContext());

        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductBasicStruct $red */
        $red = $products->get($redId);

        $this->assertTrue($red->hasExtension('inherited'));

        /** @var ArrayStruct $inheritance */
        $inheritance = $red->getExtension('inherited');

        $this->assertTrue($inheritance->get('manufacturerId'));
        $this->assertTrue($inheritance->get('unitId'));
        $this->assertTrue($inheritance->get('contextPrices'));

        /** @var ProductBasicStruct $green */
        $green = $products->get($greenId);
        $inheritance = $green->getExtension('inherited');
        $this->assertFalse($inheritance->get('contextPrices'));
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
                'tax' => ['id' => $parentTax, 'rate' => 13, 'name' => 'green'],
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

        $this->repository->create($products, ShopContext::createDefaultContext());

        $products = $this->repository->readBasic([$redId, $greenId], ShopContext::createDefaultContext());

        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductBasicStruct $red */
        $red = $products->get($redId);

        /* @var ArrayStruct $translated */
        /* @var ArrayStruct $inheritance */
        $this->assertTrue($red->hasExtension('translated'));
        $this->assertTrue($red->hasExtension('inherited'));

        $inheritance = $red->getExtension('inherited');
        $translated = $red->getExtension('translated');

        $this->assertTrue($translated->get('name'));
        $this->assertFalse($inheritance->get('name'));

        $this->assertFalse($translated->get('description'));
        $this->assertTrue($inheritance->get('description'));

        /** @var ProductBasicStruct $green */
        $green = $products->get($greenId);

        $this->assertTrue($green->hasExtension('translated'));
        $this->assertTrue($green->hasExtension('inherited'));

        $inheritance = $green->getExtension('inherited');
        $translated = $green->getExtension('translated');

        $this->assertTrue($translated->get('name'));
        $this->assertTrue($inheritance->get('name'));

        $this->assertFalse($translated->get('description'));
        $this->assertTrue($inheritance->get('description'));
    }
}
