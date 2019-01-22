<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class VariantGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
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
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->generator = $this->getContainer()->get(VariantGenerator::class);
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testGenerateOneDimension(): void
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

        $this->repository->create([$data], Context::createDefaultContext());

        $writtenEvent = $this->generator->generate($id, Context::createDefaultContext());

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        static::assertCount(2, $productWritten->getIds());

        $criteria = new Criteria($productWritten->getIds());
        $criteria->addAssociation('product.variations');
        $variants = $this->repository->read($criteria, Context::createDefaultContext());

        static::assertCount(2, $variants);

        /** @var ProductEntity $red */
        $red = $variants->filter(function (ProductEntity $detail) use ($redId) {
            return \in_array($redId, $detail->getVariations()->getIds(), true);
        })->first();

        /** @var ProductEntity $blue */
        $blue = $variants->filter(function (ProductEntity $detail) use ($blueId) {
            return \in_array($blueId, $detail->getVariations()->getIds(), true);
        })->first();

        static::assertEquals('test blue', $blue->getName());
        static::assertEquals('test red', $red->getName());

        static::assertInstanceOf(ProductEntity::class, $red);
        static::assertInstanceOf(ProductEntity::class, $blue);

        static::assertEquals(new Price(35, 60, false), $red->getPrice());
        static::assertEquals(new Price(100, 110, false), $blue->getPrice());
    }

    public function testMultiDimension(): void
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

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $writtenEvent = $this->generator->generate($id, $context);

        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);

        static::assertCount(4, $productWritten->getIds());

        $variants = $this->repository->read(new Criteria($productWritten->getIds()), $context);
        static::assertCount(4, $variants);

        $parent = $this->repository->read(new Criteria([$id]), $context)
            ->get($id);

        /** @var ProductCollection $variants */
        /** @var ProductCollection $filtered */
        $filtered = $variants->filterByVariationIds([$redId, $bigId]);
        static::assertCount(1, $filtered);
        static::assertEquals('test red big', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$blueId, $bigId]);
        static::assertCount(1, $filtered);
        static::assertEquals('test blue big', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$redId, $smallId]);
        static::assertCount(1, $filtered);
        static::assertEquals('test red small', $filtered->first()->getName());

        $filtered = $variants->filterByVariationIds([$blueId, $smallId]);
        static::assertCount(1, $filtered);
        static::assertEquals('test blue small', $filtered->first()->getName());

        foreach ($variants as $variant) {
            static::assertEquals($id, $variant->getParentId());
            static::assertEquals($parent->getPrice(), $variant->getViewData()->getPrice());
        }
    }

    public function testPagination(): void
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

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $writtenEvent = $this->generator->generate($id, $context, 0, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $productWritten->getIds());

        $variants = $this->repository->read(new Criteria($productWritten->getIds()), $context);
        static::assertCount(1, $variants);

        $writtenEvent = $this->generator->generate($id, $context, 1, 1);
        $productWritten = $writtenEvent->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $productWritten->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $id));
        $variants = $this->repository->search($criteria, $context);

        $parent = $this->repository->read(new Criteria([$id]), $context)
            ->get($id);

        /** @var ProductEntity $variant */
        foreach ($variants as $variant) {
            static::assertEquals($id, $variant->getParentId());
            static::assertEquals($parent->getPrice(), $variant->getViewData()->getPrice());
        }
    }
}
