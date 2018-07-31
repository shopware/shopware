<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VariantGeneratorTest extends KernelTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var VariantGenerator
     */
    private $generator;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        self::bootKernel();
        $this->connection = self::$container->get(Connection::class);
        $this->generator = self::$container->get(VariantGenerator::class);
        $this->repository = self::$container->get('product.repository');
        $this->connection->beginTransaction();
        parent::setUp();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testGenerateOneDimension()
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 10, 'net' => 10],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color'],
                    ],
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(Defaults::TENANT_ID));

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(2, $productWritten->getIds());

        $criteria = new ReadCriteria($productWritten->getIds());
        $criteria->addAssociation('product.variations');
        $variants = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        $this->assertCount(2, $variants);

        /** @var ProductStruct $red */
        $red = $variants->filter(function (ProductStruct $detail) use ($redId) {
            return in_array($redId, $detail->getVariations()->getIds(), true);
        })->first();

        /** @var ProductStruct $blue */
        $blue = $variants->filter(function (ProductStruct $detail) use ($blueId) {
            return in_array($blueId, $detail->getVariations()->getIds(), true);
        })->first();

        $this->assertEquals('test blue', $blue->getName());
        $this->assertEquals('test red', $red->getName());

        $this->assertInstanceOf(ProductStruct::class, $red);
        $this->assertInstanceOf(ProductStruct::class, $blue);

        $this->assertEquals(new PriceStruct(35, 60), $red->getPrice());
        $this->assertEquals(new PriceStruct(100, 110), $blue->getPrice());
    }

    public function testMultiDimension()
    {
        $id = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();
        $sizeId = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();
        $smallId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color', 'position' => 1],
                    ],
                ],
                [
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
                [
                    'option' => [
                        'id' => $bigId,
                        'name' => 'big',
                        'group' => ['id' => $sizeId, 'name' => 'size', 'position' => 2],
                    ],
                ],
                [
                    'option' => [
                        'id' => $smallId,
                        'name' => 'small',
                        'groupId' => $sizeId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(Defaults::TENANT_ID));

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(4, $productWritten->getIds());

        $variants = $this->repository->read(new ReadCriteria($productWritten->getIds()), Context::createDefaultContext(
            Defaults::TENANT_ID));
        $this->assertCount(4, $variants);

        $parent = $this->repository->read(new ReadCriteria([$id]), Context::createDefaultContext(Defaults::TENANT_ID))
            ->get($id);

        $filtered = $variants->filterByVariationIds([$redId, $bigId]);
        $this->assertCount(1, $filtered);
        $this->assertEquals('test red big', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$blueId, $bigId]);
        $this->assertCount(1, $filtered);
        $this->assertEquals('test blue big', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$redId, $smallId]);
        $this->assertCount(1, $filtered);
        $this->assertEquals('test red small', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$blueId, $smallId]);
        $this->assertCount(1, $filtered);
        $this->assertEquals('test blue small', $filtered->first()->getName());

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
        }
    }

    public function testPagination()
    {
        $id = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();
        $sizeId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'option' => [
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color'],
                    ],
                ],
                [
                    'option' => [
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
                [
                    'option' => [
                        'name' => 'big',
                        'group' => ['id' => $sizeId, 'name' => 'size'],
                    ],
                ],
                [
                    'option' => [
                        'name' => 'small',
                        'groupId' => $sizeId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(Defaults::TENANT_ID), 0, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $variants = $this->repository->read(new ReadCriteria($productWritten->getIds()), Context::createDefaultContext(
            Defaults::TENANT_ID));
        $this->assertCount(1, $variants);

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(Defaults::TENANT_ID), 1, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.parentId', $id));
        $variants = $this->repository->search($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        $parent = $this->repository->read(new ReadCriteria([$id]), Context::createDefaultContext(Defaults::TENANT_ID))
            ->get($id);

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
        }
    }
}
