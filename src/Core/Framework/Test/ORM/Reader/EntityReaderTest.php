<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\RuleRepository;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Content\Product\Struct\ProductBasicStruct;
use Shopware\Core\Defaults;
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
     * @var ProductRepository
     */
    private $repository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();

        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');

        $this->repository = self::$container->get(ProductRepository::class);
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

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$redId, $greenId], Context::createDefaultContext(Defaults::TENANT_ID));

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

        self::$container->get(RuleRepository::class)->create([
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
                'tax' => ['name' => 'test', 'rate' => 15],
                'contextPrices' => [
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
                'contextPrices' => [
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

        $products = $this->repository->readBasic([$redId, $greenId], Context::createDefaultContext(Defaults::TENANT_ID));

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

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$redId, $greenId], Context::createDefaultContext(Defaults::TENANT_ID));

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
