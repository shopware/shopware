<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ParentChildTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $categoryRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testChildrenWithMalformatDataException(): void
    {
        $parent = Uuid::randomHex();
        $child1 = Uuid::randomHex();

        $category = [
            'id' => $parent,
            'name' => 'parent',
            'children' => ['id' => $child1, 'name' => 'child 1'],
        ];

        $context = Context::createDefaultContext();

        $e = null;

        try {
            $this->categoryRepository->upsert([$category], $context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        static::assertCount(1, $e->getExceptions());
        $first = $e->getExceptions()[0];

        static::assertInstanceOf(ExpectedArrayException::class, $first);
        static::assertEquals('/0/children', $first->getPath());
    }

    public function testICanWriteChildren(): void
    {
        $parent = Uuid::randomHex();
        $child1 = Uuid::randomHex();
        $child2 = Uuid::randomHex();

        $category = [
            'id' => $parent,
            'name' => 'parent',
            'children' => [
                ['id' => $child1, 'name' => 'child 1'],
                ['id' => $child2, 'name' => 'child 2'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert([$category], $context);

        $children = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE parent_id = :id',
            ['id' => Uuid::fromHexToBytes($parent)]
        );

        $children = array_column($children, 'id');
        static::assertContains(Uuid::fromHexToBytes($child1), $children);
        static::assertContains(Uuid::fromHexToBytes($child2), $children);
    }

    public function testICanWriteNestedChildren(): void
    {
        $parent = Uuid::randomHex();
        $child1 = Uuid::randomHex();
        $child2 = Uuid::randomHex();
        $child3 = Uuid::randomHex();

        $category = [
            'id' => $parent,
            'name' => 'parent',
            'children' => [
                [
                    'id' => $child1,
                    'name' => 'child 1',
                    'children' => [
                        [
                            'id' => $child2,
                            'name' => 'child 2',
                            'children' => [
                                ['id' => $child3, 'name' => 'child 3'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert([$category], $context);

        static::assertNull(
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child1)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($child1),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child2)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($child2),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child3)]
            )
        );
    }

    public function testICanWriteTheParent(): void
    {
        $parent = Uuid::randomHex();
        $child = Uuid::randomHex();

        $category = [
            'id' => $child,
            'name' => 'child',
            'parent' => [
                'id' => $parent,
                'name' => 'parent',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert([$category], $context);

        static::assertNull(
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child)]
            )
        );
    }

    public function testICanWriteNestedParents(): void
    {
        $parent = Uuid::randomHex();
        $child1 = Uuid::randomHex();
        $child2 = Uuid::randomHex();

        $category = [
            'id' => $child2,
            'name' => 'child 2',
            'parent' => [
                'id' => $child1,
                'name' => 'child 1',
                'parent' => [
                    'id' => $parent,
                    'name' => 'parent',
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert([$category], $context);

        static::assertNull(
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child1)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($child1),
            $this->connection->fetchOne(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child2)]
            )
        );
    }

    public function testParentWithMalformatDataException(): void
    {
        $parent = Uuid::randomHex();
        $child1 = Uuid::randomHex();

        $category = [
            'id' => $child1,
            'name' => 'child',
            'parent' => $parent,
        ];

        $context = Context::createDefaultContext();

        $e = null;

        try {
            $this->categoryRepository->upsert([$category], $context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        static::assertCount(1, $e->getExceptions());
        $first = $e->getExceptions()[0];
        static::assertInstanceOf(ExpectedArrayException::class, $first);
    }
}
