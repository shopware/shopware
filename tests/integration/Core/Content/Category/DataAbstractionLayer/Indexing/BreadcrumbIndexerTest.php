<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BreadcrumbIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $repository;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('category.repository');

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testBreadcrumbAfterCreate(): void
    {
        $ids = $this->getSetUpData();

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['EN-A'], $c1->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B'], $c2->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B', 'EN-C'], $c3->getBreadcrumb());

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        );

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['DE-A'], $c1->getBreadcrumb());
        static::assertSame(['DE-A',  'DE-B'], $c2->getBreadcrumb());
        static::assertSame(['DE-A', 'DE-B', 'DE-C'], $c3->getBreadcrumb());
    }

    public function testUpdateTranslation(): void
    {
        $ids = $this->getSetUpData();

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $this->repository->update([
            [
                'id' => $ids->level1,
                'translations' => [
                    ['name' => 'EN-A-1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                ],
                'children' => [
                    [
                        'id' => $ids->level2,
                        'translations' => [
                            ['name' => 'EN-B-1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                        ],
                        'children' => [
                            [
                                'id' => $ids->level3,
                                'translations' => [
                                    ['name' => 'EN-C-1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);
        static::assertSame(['EN-A-1'], $c1->getBreadcrumb());
        static::assertSame(['EN-A-1', 'EN-B-1'], $c2->getBreadcrumb());
        static::assertSame(['EN-A-1', 'EN-B-1', 'EN-C-1'], $c3->getBreadcrumb());

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        );

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['DE-A'], $c1->getBreadcrumb());
        static::assertSame(['DE-A', 'DE-B'], $c2->getBreadcrumb());
        static::assertSame(['DE-A', 'DE-B', 'DE-C'], $c3->getBreadcrumb());
    }

    public function testLanguageInheritance(): void
    {
        $ids = $this->getSetUpData();

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $this->repository->update([
            [
                'id' => $ids->level1,
                'translations' => [
                    ['name' => null, 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => $ids->level2,
                        'translations' => [
                            ['name' => null, 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $ids->level3,
                                'translations' => [
                                    ['name' => null, 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['EN-A'], $c1->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B'], $c2->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B', 'EN-C'], $c3->getBreadcrumb());

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        );

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['EN-A'], $c1->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B'], $c2->getBreadcrumb());
        static::assertSame(['EN-A', 'EN-B', 'EN-C'], $c3->getBreadcrumb());

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        );

        $this->repository->update([
            [
                'id' => $ids->level2,
                'translations' => [
                    ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                ],
            ],
        ], $context);

        $categories = $this->repository
            ->search(new Criteria($ids->all()), $context)
            ->getEntities();

        $c1 = $categories->get($ids->level1);
        $c2 = $categories->get($ids->level2);
        $c3 = $categories->get($ids->level3);

        static::assertInstanceOf(CategoryEntity::class, $c1);
        static::assertInstanceOf(CategoryEntity::class, $c2);
        static::assertInstanceOf(CategoryEntity::class, $c3);

        static::assertSame(['EN-A'], $c1->getBreadcrumb());
        static::assertSame(['EN-A', 'DE-B'], $c2->getBreadcrumb());
        static::assertSame(['EN-A', 'DE-B', 'EN-C'], $c3->getBreadcrumb());
    }

    private function getSetUpData(): SetUpData
    {
        $level1 = Uuid::randomHex();
        $level2 = Uuid::randomHex();
        $level3 = Uuid::randomHex();

        $data = [
            [
                'id' => $level1,
                'translations' => [
                    ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => $level2,
                        'translations' => [
                            ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $level3,
                                'translations' => [
                                    ['name' => 'EN-C', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-C', 'languageId' => $this->deLanguageId],
                                ],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $this->repository->create($data, $context);

        return new SetUpData($level1, $level2, $level3);
    }
}

/**
 * @internal
 */
class SetUpData
{
    public string $level1;

    public string $level2;

    public string $level3;

    public function __construct(
        string $level1,
        string $level2,
        string $level3
    ) {
        $this->level1 = $level1;
        $this->level2 = $level2;
        $this->level3 = $level3;
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        return [$this->level1, $this->level2, $this->level3];
    }
}
