<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Navigation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NavigationSynchronizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $navigationRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->navigationRepository = $this->getContainer()->get('navigation.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');

        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM navigation');
    }

    public function testAddCategory(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();
        $id4 = Uuid::uuid4()->getHex();
        $id5 = Uuid::uuid4()->getHex();
        $id6 = Uuid::uuid4()->getHex();

        $categories = [
            [
                'id' => $id1,
                'name' => 'main',
                'children' => [
                    [
                        'id' => $id2,
                        'name' => 'level 1',
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create($categories, $context);

        $navigations = [
            [
                'id' => $id1,
                'categoryId' => $id1,
                'name' => 'main',
                'children' => [
                    [
                        'id' => $id2,
                        'name' => 'level 1',
                        'categoryId' => $id2,
                    ],
                ],
            ],
        ];

        $this->navigationRepository->create($navigations, $context);

        $navigations = $this->navigationRepository->search(new Criteria([$id1, $id2]), $context);

        static::assertNull($navigations->get($id1)->getParentId());
        static::assertSame($id1, $navigations->get($id2)->getParentId());

        $this->categoryRepository->create(
            [
                ['id' => $id3, 'parentId' => $id2, 'name' => 'level 2'],
                ['id' => $id4, 'parentId' => $id3, 'name' => 'level 2'],
                ['id' => $id5, 'parentId' => $id4, 'name' => 'level 2'],
                ['id' => $id6, 'parentId' => $id3, 'name' => 'level 2'],
            ],
            $context
        );

        $navigations = $this->navigationRepository->search(new Criteria(), $context);

        static::assertCount(6, $navigations);

        $navigation3 = $navigations->filter(function (NavigationEntity $entity) use ($id3) {
            return $entity->getCategoryId() === $id3;
        })->first();

        $navigation4 = $navigations->filter(function (NavigationEntity $entity) use ($id4) {
            return $entity->getCategoryId() === $id4;
        })->first();

        static::assertInstanceOf(NavigationEntity::class, $navigation3);
        static::assertInstanceOf(NavigationEntity::class, $navigation4);

        foreach ($navigations as $navigation) {
            if (in_array($navigation->getId(), [$id1, $id2], true)) {
                continue;
            }

            /** @var NavigationEntity $navigation */
            if ($navigation->getCategoryId() === $id3) {
                static::assertSame($id2, $navigation->getParentId());
                continue;
            }

            if ($navigation->getCategoryId() === $id4) {
                static::assertSame($navigation3->getId(), $navigation->getParentId());
                continue;
            }

            if ($navigation->getCategoryId() === $id5) {
                static::assertSame($navigation4->getId(), $navigation->getParentId());
                continue;
            }

            if ($navigation->getCategoryId() === $id6) {
                static::assertSame($navigation3->getId(), $navigation->getParentId());
                continue;
            }

            static::fail(print_r($navigation, true));
        }
    }

    public function testDeleteCategory(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();
        $id4 = Uuid::uuid4()->getHex();

        $categories = [
            [
                'id' => $id1,
                'name' => 'main',
                'children' => [
                    ['id' => $id2, 'name' => 'level 1'],
                    ['id' => $id3, 'name' => 'level 1'],
                    ['id' => $id4, 'name' => 'level 1'],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create($categories, $context);

        $navigations = [
            [
                'id' => $id1,
                'categoryId' => $id1,
                'name' => 'main',
                'children' => [
                    ['id' => $id2, 'categoryId' => $id2, 'name' => 'level 1'],
                    ['id' => $id3, 'categoryId' => $id3, 'name' => 'level 1'],
                    ['id' => $id4, 'categoryId' => $id4, 'name' => 'level 1'],
                ],
            ],
        ];

        $this->navigationRepository->create($navigations, $context);

        $this->categoryRepository->delete(
            [
                ['id' => $id3],
                ['id' => $id4],
            ],
            $context
        );

        $navigations = $this->navigationRepository->search(new Criteria(), $context);

        static::assertCount(2, $navigations);
        static::assertTrue($navigations->has($id1));
        static::assertTrue($navigations->has($id2));

        static::assertFalse($navigations->has($id3));
        static::assertFalse($navigations->has($id4));

        $this->categoryRepository->delete(
            [['id' => $id1]],
            $context
        );

        $navigations = $this->navigationRepository->search(new Criteria(), $context);
        static::assertCount(0, $navigations);
    }

    public function testMoveCategory(): void
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();
        $id4 = Uuid::uuid4()->getHex();
        $id5 = Uuid::uuid4()->getHex();
        $id6 = Uuid::uuid4()->getHex();

        $categories = [
            [
                'id' => $id1,
                'name' => 'main',
                'children' => [
                    [
                        'id' => $id2,
                        'name' => 'level 1',
                        'children' => [
                            ['id' => $id3, 'name' => 'level 2'],
                            ['id' => $id4, 'name' => 'level 2'],
                        ],
                    ],
                    [
                        'id' => $id5,
                        'name' => 'level 1',
                        'children' => [
                            ['id' => $id6, 'name' => 'level 2'],
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create($categories, $context);

        $navigations = [
            [
                'id' => $id1,
                'categoryId' => $id1,
                'name' => 'main',
                'children' => [
                    [
                        'id' => $id2,
                        'name' => 'level 1',
                        'categoryId' => $id2,
                        'children' => [
                            ['id' => $id3, 'categoryId' => $id3, 'name' => 'level 2'],
                            ['id' => $id4, 'categoryId' => $id4, 'name' => 'level 2'],
                        ],
                    ],
                    [
                        'id' => $id5,
                        'categoryId' => $id5,
                        'name' => 'level 1',
                        'children' => [
                            ['id' => $id6, 'categoryId' => $id6, 'name' => 'level 2'],
                        ],
                    ],
                ],
            ],
        ];

        $idArray = [$id1, $id2, $id3, $id4, $id5, $id6];

        $this->navigationRepository->create($navigations, $context);

        $navigations = $this->navigationRepository->search(new Criteria($idArray), $context);

        static::assertNull($navigations->get($id1)->getParentId());
        static::assertSame($id1, $navigations->get($id2)->getParentId());

        static::assertSame($id2, $navigations->get($id3)->getParentId());
        static::assertSame($id2, $navigations->get($id4)->getParentId());

        static::assertSame($id1, $navigations->get($id5)->getParentId());
        static::assertSame($id5, $navigations->get($id6)->getParentId());

        $this->categoryRepository->update([
            ['id' => $id3, 'parentId' => $id6],
            ['id' => $id4, 'parentId' => $id1],
            ['id' => $id2, 'parentId' => $id3],
            ['id' => $id6, 'parentId' => $id1],
        ], $context);

        $navigations = $this->navigationRepository->search(new Criteria($idArray), $context);

        static::assertNull($navigations->get($id1)->getParentId());

        static::assertSame($id3, $navigations->get($id2)->getParentId());

        static::assertSame($id6, $navigations->get($id3)->getParentId());
        static::assertSame($id1, $navigations->get($id4)->getParentId());

        static::assertSame($id1, $navigations->get($id5)->getParentId());
        static::assertSame($id1, $navigations->get($id6)->getParentId());
    }
}
