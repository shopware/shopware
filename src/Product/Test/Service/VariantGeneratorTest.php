<?php declare(strict_types=1);

namespace Shopware\Product\Test\Service;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Service\VariantGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VariantGeneratorTest extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ProductRepository
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
        $kernel = self::bootKernel();
        $this->container = $kernel->getContainer();
        $this->connection = $this->container->get(Connection::class);
        $this->generator = $this->container->get(VariantGenerator::class);
        $this->repository = $this->container->get(ProductRepository::class);
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
        $id = Uuid::uuid4()->toString();
        $colorId = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => 10,
            'tax' => ['name' => 'test', 'rate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color']
                    ]
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'name' => 'blue',
                        'groupId' => $colorId
                    ]
                ]
            ]
        ];

        $this->repository->create([$data], ShopContext::createDefaultContext());

        $writtenEvent = $this->generator->generate($id, ShopContext::createDefaultContext());

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(2, $productWritten->getIds());

        $variants = $this->repository->readBasic($productWritten->getIds(), ShopContext::createDefaultContext());
        $this->assertCount(2, $variants);

        $parent = $this->repository->readBasic([$id], ShopContext::createDefaultContext())
            ->get($id);

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
            $this->assertEquals($parent->getName(), $variant->getName());
        }
    }

    public function testMultiDimension()
    {
        $id = Uuid::uuid4()->toString();
        $colorId = Uuid::uuid4()->toString();
        $sizeId = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => 10,
            'tax' => ['name' => 'test', 'rate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color']
                    ]
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'name' => 'blue',
                        'groupId' => $colorId
                    ]
                ],
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'name' => 'big',
                        'group' => ['id' => $sizeId, 'name' => 'size']
                    ]
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'name' => 'small',
                        'groupId' => $sizeId
                    ]
                ]
            ]
        ];

        $this->repository->create([$data], ShopContext::createDefaultContext());

        $writtenEvent = $this->generator->generate($id, ShopContext::createDefaultContext());

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(4, $productWritten->getIds());

        $variants = $this->repository->readBasic($productWritten->getIds(), ShopContext::createDefaultContext());
        $this->assertCount(4, $variants);

        $parent = $this->repository->readBasic([$id], ShopContext::createDefaultContext())
            ->get($id);

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
            $this->assertEquals($parent->getName(), $variant->getName());
        }
    }


    public function testPagination()
    {
        $id = Uuid::uuid4()->toString();
        $colorId = Uuid::uuid4()->toString();
        $sizeId = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => 10,
            'tax' => ['name' => 'test', 'rate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => 'color']
                    ]
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'name' => 'blue',
                        'groupId' => $colorId
                    ]
                ],
                [
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'name' => 'big',
                        'group' => ['id' => $sizeId, 'name' => 'size']
                    ]
                ],
                [
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'name' => 'small',
                        'groupId' => $sizeId
                    ]
                ]
            ]
        ];

        $this->repository->create([$data], ShopContext::createDefaultContext());

        $writtenEvent = $this->generator->generate($id, ShopContext::createDefaultContext(), 0, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $variants = $this->repository->readBasic($productWritten->getIds(), ShopContext::createDefaultContext());
        $this->assertCount(1, $variants);

        $writtenEvent = $this->generator->generate($id, ShopContext::createDefaultContext(), 1, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        $this->assertCount(1, $productWritten->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.parentId', $id));
        $variants = $this->repository->search($criteria, ShopContext::createDefaultContext());

        $parent = $this->repository->readBasic([$id], ShopContext::createDefaultContext())
            ->get($id);

        foreach ($variants as $variant) {
            $this->assertEquals($id, $variant->getParentId());
            $this->assertEquals($parent->getPrice(), $variant->getPrice());
            $this->assertEquals($parent->getName(), $variant->getName());
        }
    }

    public function testGenerateHighDimension()
    {
        $id = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => 10,
            'tax' => ['name' => 'test', 'rate' => 19],
            'manufacturer' => ['name' => 'test'],
            'configurators' => $this->generateConfiguratorData([
                'size' => ['xl', 'xxl', 'l', 's', 'm', 'ms'],
                'color' => ['red', 'green', 'blue', 'black', 'white'],
                'material' => ['wood', 'cotton', 'silk']
            ])
        ];

        $this->repository->create([$data], ShopContext::createDefaultContext());
        $event = $this->generator->generate($id, ShopContext::createDefaultContext());

        $productEvent = $event->getEventByDefinition(ProductDefinition::class);

        $this->assertCount(90, $productEvent->getIds());
    }

    /**
     * e.G.
     *  [
     *      'size' => [xl, l, s],
     *      'color' => [red, green, blue]
     *  ]
     * @param $options
     *
     * @return array
     */
    private function generateConfiguratorData($groups): array
    {
        $data = [];
        foreach ($groups as $groupName => $options) {
            $groupId = Uuid::uuid4()->toString();

            foreach ($options as $optionName) {
                $data[] = [
                    'option' => [
                        'name' => $optionName,
                        'group' => ['id' => $groupId, 'name' => $groupName]
                    ]
                ];
            }
        }

        return $data;
    }
}
