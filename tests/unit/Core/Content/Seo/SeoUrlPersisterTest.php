<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlPersister::class)]
class SeoUrlPersisterTest extends TestCase
{
    public function testUpdateSeoUrlsWithNewSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path2',
                'seoPathInfo' => 'path2',
            ],
        ];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('fetchOne')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('executeStatement');

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    /**
     * Regression for shopware/shopware#4413. An existing canonical SEO URL is only replaced when the
     * update is a real change:
     *
     * 1. automatic template regeneration ({@see SeoUrlPersister::updateSeoUrls()}) must never replace a
     *    manually modified (write-protected) URL that still has a non-empty path,
     * 2. a different path is always a real change,
     * 3. when the path is unchanged the update is skipped to avoid duplicate rows — except for an
     *    explicit overwrite ({@see SeoUrlPersister::forceUpdateSeoUrls()}) that flips the isModified
     *    flag, which is how an admin "reset to template" drops the write-protection even when the
     *    manual value already equals the template output.
     */
    #[DataProvider('canonicalUpdateProvider')]
    public function testExistingCanonicalSeoUrlIsOnlyReplacedByARealChange(
        string $existingPath,
        bool $existingIsModified,
        string $newPath,
        bool $newIsModified,
        bool $overwrite,
        bool $expectRowWritten
    ): void {
        $writtenValues = $this->persistSeoUrlUpdate(
            ['seoPathInfo' => $existingPath, 'isModified' => $existingIsModified],
            ['seoPathInfo' => $newPath, 'isModified' => $newIsModified],
            $overwrite
        );

        if ($expectRowWritten) {
            static::assertContains($newPath, $writtenValues, \sprintf('Expected a SEO URL row for "%s" to be written.', $newPath));

            return;
        }

        static::assertNotContains($newPath, $writtenValues, \sprintf('Expected no SEO URL row to be written for "%s".', $newPath));
    }

    /**
     * @return \Generator<string, array{existingPath: string, existingIsModified: bool, newPath: string, newIsModified: bool, overwrite: bool, expectRowWritten: bool}>
     */
    public static function canonicalUpdateProvider(): \Generator
    {
        // the write-protection guard: regeneration may not throw away the manual path
        yield 'automatic regeneration keeps a write-protected path' => [
            'existingPath' => 'manual-path',
            'existingIsModified' => true,
            'newPath' => 'template-path',
            'newIsModified' => false,
            'overwrite' => false,
            'expectRowWritten' => false,
        ];

        // an explicit overwrite bypasses the guard and resets the URL to the template path
        yield 'explicit overwrite resets a write-protected path to the template path' => [
            'existingPath' => 'manual-path',
            'existingIsModified' => true,
            'newPath' => 'template-path',
            'newIsModified' => false,
            'overwrite' => true,
            'expectRowWritten' => true,
        ];

        // an admin editing the write-protected URL to another manual path must be persisted
        yield 'explicit overwrite persists a manual edit of a write-protected path' => [
            'existingPath' => 'manual-path',
            'existingIsModified' => true,
            'newPath' => 'manual-path-edited',
            'newIsModified' => true,
            'overwrite' => true,
            'expectRowWritten' => true,
        ];

        // nothing changed at all: re-saving the identical URL must not create a duplicate row
        yield 'explicit overwrite of an unchanged path and flag creates no duplicate' => [
            'existingPath' => 'manual-path',
            'existingIsModified' => true,
            'newPath' => 'manual-path',
            'newIsModified' => true,
            'overwrite' => true,
            'expectRowWritten' => false,
        ];

        // same-path reset: only the write-protection flag changes, and that must still be persisted
        yield 'explicit overwrite drops the write protection on an unchanged path' => [
            'existingPath' => 'red-shoe',
            'existingIsModified' => true,
            'newPath' => 'red-shoe',
            'newIsModified' => false,
            'overwrite' => true,
            'expectRowWritten' => true,
        ];

        // without an explicit overwrite the same situation keeps being skipped
        yield 'automatic regeneration of an unchanged path keeps being skipped' => [
            'existingPath' => 'red-shoe',
            'existingIsModified' => true,
            'newPath' => 'red-shoe',
            'newIsModified' => false,
            'overwrite' => false,
            'expectRowWritten' => false,
        ];
    }

    public function testForceUpdateSeoUrlsPersistsNewSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
        ];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $connection->expects($this->never())
            ->method('fetchOne');

        $connection->expects($this->never())
            ->method('executeStatement');

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->forceUpdateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    public function testUpdateSeoUrlsWithInuseSeoPaths(): void
    {
        $connection = $this->createMock(Connection::class);
        $seoUrlPersister = $this->createSeoUrlPersister($connection);

        $seoUrls = [
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path1',
                'seoPathInfo' => 'path1',
            ],
            [
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'salesChannelId' => Uuid::randomHex(),
                'routeName' => 'test-route',
                'pathInfo' => 'path2',
                'seoPathInfo' => 'path2',
            ],
        ];

        $id1 = Uuid::randomBytes();
        $id2 = Uuid::randomBytes();
        $expectedIds = [$id1, $id2];

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'id1',
                    'languageId' => Uuid::randomHex(),
                    'salesChannelId' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'routeName' => 'test-route',
                ],
                [
                    'id' => 'id2',
                    'languageId' => Uuid::randomHex(),
                    'salesChannelId' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'routeName' => 'test-route',
                ],
            ]);

        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls($id1, $id2);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id IN (:ids)',
                ['ids' => $expectedIds],
                ['ids' => ArrayParameterType::BINARY]
            );

        $seoChannel = new SalesChannelEntity();
        $seoChannel->setId(Uuid::randomHex());

        $seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'test-route',
            [
                'foreignKey' => Uuid::randomHex(),
            ],
            $seoUrls,
            $seoChannel
        );
    }

    /**
     * Runs an update for one entity that already has a canonical SEO URL and returns every value that was
     * written to the database, so a test can check whether a row carrying a given `seo_path_info` was
     * inserted. This is the only place that knows how the persister talks to the database: the canonical
     * URL is served through the query builder used by `findCanonicalPaths()`, the writes are captured from
     * the insert queue, which executes statements on the connection.
     *
     * @param array{seoPathInfo: string, isModified: bool} $existing the canonical SEO URL already stored
     * @param array{seoPathInfo: string, isModified: bool} $update the SEO URL the persister is asked to store
     *
     * @return list<mixed> all values written to the database
     */
    private function persistSeoUrlUpdate(array $existing, array $update, bool $overwrite): array
    {
        $foreignKey = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $routeName = 'frontend.detail.page';

        $canonicalResult = static::createStub(Result::class);
        $canonicalResult->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::randomHex(),
                'foreignKey' => $foreignKey,
                'salesChannelId' => $salesChannelId,
                'isModified' => $existing['isModified'] ? 1 : 0,
                'seoPathInfo' => $existing['seoPathInfo'],
            ],
        ]);

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($canonicalResult);

        $writtenValues = [];
        $transactions = 0;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // the writes happen inside a retryable transaction, so the double has to really run the closure,
        // otherwise no statement is ever reached and every assertion on the written values passes vacuously
        $connection->expects($this->atLeastOnce())
            ->method('transactional')
            ->willReturnCallback(function (\Closure $closure) use (&$transactions, $connection) {
                ++$transactions;

                return $closure($connection);
            });

        // no other entity already occupies the generated path
        $connection->method('fetchAllAssociative')->willReturn([]);

        $connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$writtenValues): int {
                foreach ($params as $value) {
                    $writtenValues[] = $value;
                }

                return 1;
            });

        $seoUrl = [
            'foreignKey' => $foreignKey,
            'salesChannelId' => $salesChannelId,
            'routeName' => $routeName,
            'pathInfo' => '/detail/' . $foreignKey,
            'seoPathInfo' => $update['seoPathInfo'],
            'isModified' => $update['isModified'],
        ];

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $seoUrlPersister = $this->createSeoUrlPersister($connection);
        $context = Context::createDefaultContext();

        if ($overwrite) {
            $seoUrlPersister->forceUpdateSeoUrls($context, $routeName, [$foreignKey], [$seoUrl], $salesChannel);
        } else {
            $seoUrlPersister->updateSeoUrls($context, $routeName, [$foreignKey], [$seoUrl], $salesChannel);
        }

        static::assertGreaterThan(0, $transactions, 'The connection double did not run the transaction closure, so no write could have been observed.');

        return $writtenValues;
    }

    private function createSeoUrlPersister(Connection $connection): SeoUrlPersister
    {
        return new SeoUrlPersister(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new NativeClock()
        );
    }
}
