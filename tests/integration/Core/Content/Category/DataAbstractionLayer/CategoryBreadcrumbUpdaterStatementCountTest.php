<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Breadcrumb updates are built from a fixed set of bulk queries. The number of
 * SQL statements the updater sends must therefore stay flat, no matter how many
 * categories are handed to it.
 *
 * @internal
 */
#[Package('discovery')]
class CategoryBreadcrumbUpdaterStatementCountTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * Both sizes stay below CategoryBreadcrumbUpdater::WRITE_CHUNK_SIZE. A chunk
     * boundary between them would add an INSERT for a legitimate reason, and the
     * failure message would then report an N+1 that does not exist.
     */
    private const SMALL_COUNT = 5;

    private const LARGE_COUNT = 25;

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

    public function testStatementCountDoesNotGrowWithTheNumberOfCategories(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->createCategories($context);

        $this->clearBreadcrumbs($ids);

        $this->updater->update([$ids[0]], $context);

        $small = $this->countSqlStatements(function () use ($ids, $context): void {
            $this->updater->update(\array_slice($ids, 0, self::SMALL_COUNT), $context);
        });

        $large = $this->countSqlStatements(function () use ($ids, $context): void {
            $this->updater->update($ids, $context);
        });

        static::assertSame(
            \count($ids) * 2,
            $this->countWrittenBreadcrumbs($ids),
            'CategoryBreadcrumbUpdater::update() did not write a breadcrumb for every category and language. A statement count that stays flat because the updater writes nothing proves nothing.'
        );

        static::assertSame(
            $small,
            $large,
            \sprintf(
                'CategoryBreadcrumbUpdater::update() sent %d SQL statements for %d categories and %d for %d: the count grows with the number of categories.',
                $small,
                self::SMALL_COUNT,
                $large,
                self::LARGE_COUNT
            )
        );
    }

    /**
     * MySQL's `Questions` session counter increments once per statement sent on
     * this connection, so it measures DBAL round trips rather than rows. The
     * closing read is itself a statement and is subtracted again.
     */
    private function countSqlStatements(callable $callback): int
    {
        $connection = static::getContainer()->get(Connection::class);

        $before = $this->readQuestions($connection);

        $callback();

        $after = $this->readQuestions($connection);

        return $after - $before - 1;
    }

    private function readQuestions(Connection $connection): int
    {
        $row = $connection->fetchAssociative(<<<'SQL'
            SHOW SESSION STATUS LIKE 'Questions'
            SQL);

        \assert(\is_array($row));

        return (int) $row['Value'];
    }

    /**
     * @param list<string> $ids
     */
    private function clearBreadcrumbs(array $ids): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            <<<'SQL'
                UPDATE `category_translation` SET `breadcrumb` = NULL WHERE `category_id` IN (:ids)
                SQL,
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $ids
     */
    private function countWrittenBreadcrumbs(array $ids): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM `category_translation`
                WHERE `category_id` IN (:ids) AND `breadcrumb` IS NOT NULL
                SQL,
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
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
        for ($i = 0; $i < self::LARGE_COUNT; ++$i) {
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
