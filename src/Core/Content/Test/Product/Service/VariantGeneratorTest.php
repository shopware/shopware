<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Content\Product\Struct\ProductDetailStruct;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VariantGeneratorTest extends KernelTestCase
{
    /**
     * @var \Shopware\Core\Content\Product\ProductRepository
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
        $this->repository = self::$container->get(ProductRepository::class);
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
            'tax' => ['name' => 'test', 'rate' => 19],
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

        $this->repository->create([$data], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(2, $productWritten->getIds());

        $variants = $this->repository->readDetail($productWritten->getIds(), Context::createDefaultContext(
            \Shopware\Core\Defaults::TENANT_ID));
        $this->assertCount(2, $variants);

        /** @var ProductDetailStruct $red */
        $red = $variants->filter(function (ProductDetailStruct $detail) use ($redId) {
            return in_array($redId, $detail->getVariations()->getIds(), true);
        })->first();

        /** @var ProductDetailStruct $blue */
        $blue = $variants->filter(function (ProductDetailStruct $detail) use ($blueId) {
            return in_array($blueId, $detail->getVariations()->getIds(), true);
        })->first();

        $this->assertEquals('test blue', $blue->getName());
        $this->assertEquals('test red', $red->getName());

        $this->assertInstanceOf(ProductDetailStruct::class, $red);
        $this->assertInstanceOf(ProductDetailStruct::class, $blue);

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
            'tax' => ['name' => 'test', 'rate' => 19],
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

        $this->repository->create([$data], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(4, $productWritten->getIds());

        $variants = $this->repository->readBasic($productWritten->getIds(), Context::createDefaultContext(
            \Shopware\Core\Defaults::TENANT_ID));
        $this->assertCount(4, $variants);

        $parent = $this->repository->readBasic([$id], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID))
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
            'tax' => ['name' => 'test', 'rate' => 19],
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

        $this->repository->create([$data], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID), 0, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $variants = $this->repository->readBasic($productWritten->getIds(), Context::createDefaultContext(
            \Shopware\Core\Defaults::TENANT_ID));
        $this->assertCount(1, $variants);

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID), 1, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.parentId', $id));
        $variants = $this->repository->search($criteria, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $parent = $this->repository->readBasic([$id], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID))
            ->get($id);

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
        }
    }

    public function testGenerateHighDimension()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Variant Generator',
            'price' => ['gross' => 50, 'net' => 25],
            'tax' => ['name' => 'test', 'rate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => $this->generateConfiguratorData([
                'size' => ['xl', 'xxl', 'l', 's', 'm', 'ms'],
                'color' => ['red', 'green', 'blue', 'black', 'white'],
                'material' => ['wood', 'cotton', 'silk'],
            ]),
        ];

        $this->repository->create([$data], Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));
        $event = $this->generator->generate($id, Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID));

        $productEvent = $event->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(90, $productEvent->getIds());
    }

    /**
     * e.G.
     *  [
     *      'size' => [xl, l, s],
     *      'color' => [red, green, blue]
     *  ]
     *
     * @param $options
     *
     * @return array
     */
    private function generateConfiguratorData($groups): array
    {
        $data = [];
        foreach ($groups as $groupName => $options) {
            $groupId = Uuid::uuid4()->getHex();

            foreach ($options as $optionName) {
                $data[] = [
                    'option' => [
                        'name' => $optionName,
                        'group' => ['id' => $groupId, 'name' => $groupName],
                    ],
                ];
            }
        }

        return $data;
    }
}
