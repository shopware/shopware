<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductTagTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $red = Uuid::randomHex();
        $green = Uuid::randomHex();
        $blue = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createProduct($id1, [
            ['id' => $red, 'name' => 'red'],
            ['id' => $blue, 'name' => 'blue'],
        ]);

        $this->createProduct($id2, [
            ['id' => $green, 'name' => 'green'],
            ['id' => $red, 'name' => 'red'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.tagIds', [$red]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.tagIds', [$green]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.tagIds', [$notAssigned]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());
    }

    public function testNotEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $red = Uuid::randomHex();
        $green = Uuid::randomHex();
        $blue = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createProduct($id1, [
            ['id' => $red, 'name' => 'red'],
            ['id' => $blue, 'name' => 'blue'],
        ]);

        $this->createProduct($id2, [
            ['id' => $green, 'name' => 'green'],
            ['id' => $red, 'name' => 'red'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('product.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('product.tagIds', [$green]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('product.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());
    }

    private function createProduct(string $id, array $tags): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tags' => $tags,
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test'],
            'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
        ];
        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);
    }
}
