<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\Trait\SqlStatementCountBehaviour;

/**
 * Breadcrumb updates are built from a fixed set of bulk queries. The number of
 * SQL statements the updater sends must therefore stay flat, no matter how many
 * categories are handed to it.
 *
 * @internal
 */
#[Package('discovery')]
class CategoryBreadcrumbUpdaterQueryBudgetTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SqlStatementCountBehaviour;

    private const CATEGORY_COUNT = 100;

    private const STATEMENT_BUDGET = 10;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private CategoryBreadcrumbUpdater $updater;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->updater = static::getContainer()->get(CategoryBreadcrumbUpdater::class);
    }

    public function testStatementCountStaysWithinBudget(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->createCategories($context);

        $this->updater->update([$ids[0]], $context);

        $statements = $this->countSqlStatements(function () use ($ids, $context): void {
            $this->updater->update($ids, $context);
        });

        static::assertLessThanOrEqual(
            self::STATEMENT_BUDGET,
            $statements,
            \sprintf(
                'CategoryBreadcrumbUpdater::update() sent %d SQL statements for %d categories, budget is %d.',
                $statements,
                \count($ids),
                self::STATEMENT_BUDGET
            )
        );
    }

    /**
     * @return list<string>
     */
    private function createCategories(Context $context): array
    {
        $deLanguageId = $this->getDeDeLanguageId();

        $ids = [];
        $children = [];
        for ($i = 0; $i < self::CATEGORY_COUNT; ++$i) {
            $id = Uuid::randomHex();
            $ids[] = $id;
            $children[] = [
                'id' => $id,
                'translations' => [
                    ['name' => 'EN child ' . $i, 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE child ' . $i, 'languageId' => $deLanguageId],
                ],
            ];
        }

        $this->categoryRepository->create([
            [
                'id' => Uuid::randomHex(),
                'translations' => [
                    ['name' => 'EN root', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE root', 'languageId' => $deLanguageId],
                ],
                'children' => $children,
            ],
        ], $context);

        return $ids;
    }
}
