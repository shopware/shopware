<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ParentChildTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testChildrenWithMalformatDataException()
    {
        $parent = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();

        $category = [
            'id' => $parent,
            'name' => 'parent',
            'children' => ['id' => $child1, 'name' => 'child 1'],
        ];

        $context = Context::createDefaultContext();

        $e = null;
        try {
            $this->categoryRepository->upsert([$category], $context);
        } catch (\Exception $e) {
        }

        /** @var WriteStackException $e */
        static::assertInstanceOf(WriteStackException::class, $e);

        static::assertCount(1, $e->getExceptions());
        $first = $e->getExceptions()[0];

        static::assertInstanceOf(MalformatDataException::class, $first);
        static::assertEquals('/children', $first->getPath());
    }

    public function testICanWriteChildren()
    {
        $parent = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();
        $child2 = Uuid::uuid4()->getHex();

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

        $children = $this->connection->fetchAll(
            'SELECT * FROM category WHERE parent_id = :id',
            ['id' => Uuid::fromHexToBytes($parent)]
        );

        $children = array_column($children, 'id');
        static::assertContains(Uuid::fromHexToBytes($child1), $children);
        static::assertContains(Uuid::fromHexToBytes($child2), $children);
    }

    public function testICanWriteNestedChildren()
    {
        $parent = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();
        $child2 = Uuid::uuid4()->getHex();
        $child3 = Uuid::uuid4()->getHex();

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
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child1)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($child1),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child2)]
            )
        );

        static::assertEquals(
            Uuid::fromHexToBytes($child2),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child3)]
            )
        );
    }

    public function testICanWriteTheParent()
    {
        $parent = Uuid::uuid4()->getHex();
        $child = Uuid::uuid4()->getHex();

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
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child)]
            )
        );
    }

    public function testICanWriteNestedParents()
    {
        $parent = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();
        $child2 = Uuid::uuid4()->getHex();

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
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($parent)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($parent),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child1)]
            )
        );
        static::assertEquals(
            Uuid::fromHexToBytes($child1),
            $this->connection->fetchColumn(
                'SELECT parent_id FROM category WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($child2)]
            )
        );
    }

    public function testParentWithMalformatDataException()
    {
        $parent = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();

        $category = [
            'id' => $child1,
            'name' => 'child',
            'parent' => $parent,
        ];

        $context = Context::createDefaultContext();

        $e = null;
        try {
            $this->categoryRepository->upsert([$category], $context);
        } catch (\Exception $e) {
        }

        /** @var WriteStackException $e */
        static::assertInstanceOf(WriteStackException::class, $e);

        static::assertCount(1, $e->getExceptions());
        $first = $e->getExceptions()[0];
        static::assertInstanceOf(MalformatDataException::class, $first);
    }
}
