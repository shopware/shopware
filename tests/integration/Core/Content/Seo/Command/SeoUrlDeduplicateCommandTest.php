<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Command\SeoUrlDeduplicateCommand;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
class SeoUrlDeduplicateCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private Connection $connection;

    private string $salesChannelA;

    private string $salesChannelB;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        // Ensure we have two storefront sales channels to satisfy FK constraints
        $this->salesChannelA = Uuid::randomHex();
        $this->salesChannelB = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($this->salesChannelA, 'sc-a');
        $this->createStorefrontSalesChannelContext($this->salesChannelB, 'sc-b');

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testSoftDeleteKeepsOldestAndDeletesOthers(): void
    {
        $route = 'integration.seo.dedupe.case1';
        $fk = Uuid::randomHex();

        // Create two canonical entries with identical grouping keys in different sales channels
        [$keepId, $removeId] = $this->createDuplicateCanonicalGroup($route, $fk, 'detail/123', 'awesome-product', '2020-01-01 00:00:00.000', '2021-01-01 00:00:00.000');

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$route], '--soft-delete' => true]);

        $display = $tester->getDisplay();
        static::assertStringContainsString('Duplicate groups found:', $display);
        static::assertStringContainsString('Redundant canonical entries to delete: 1', $display);

        $kept = $this->fetchSeoUrl($keepId);
        static::assertNotNull($kept);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $kept */
        static::assertNotNull($kept['sales_channel_id'], 'Kept entry stays per-channel');
        static::assertSame(1, (int) $kept['is_canonical']);

        $removed = $this->fetchSeoUrl($removeId);
        static::assertNotNull($removed, 'Duplicate entry should still exist after soft-delete');
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $removed */
        static::assertNull($removed['is_canonical']);
        static::assertSame(1, (int) $removed['is_deleted']);
    }

    public function testHardDeleteDeletesDuplicates(): void
    {
        $route = 'integration.seo.dedupe.case2';
        $fk = Uuid::randomHex();

        [$keepId, $removeId] = $this->createDuplicateCanonicalGroup($route, $fk, 'detail/234', 'awesome-234', '2020-01-01 00:00:00.000', '2021-01-01 00:00:00.000');

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$route], '--hard-delete' => true]);

        $kept = $this->fetchSeoUrl($keepId);
        static::assertNotNull($kept);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $kept */
        static::assertNotNull($kept['sales_channel_id']);
        static::assertSame(1, (int) $kept['is_canonical']);

        $removed = $this->fetchSeoUrl($removeId);
        static::assertNull($removed, 'Duplicate entry should be physically deleted');
    }

    public function testDryRunDoesNotChangeData(): void
    {
        $route = 'integration.seo.dedupe.case3';
        $fk = Uuid::randomHex();

        [$keepId, $removeId] = $this->createDuplicateCanonicalGroup($route, $fk, 'detail/345', 'awesome-345', '2020-01-01 00:00:00.000', '2021-01-01 00:00:00.000');

        $beforeKeep = $this->fetchSeoUrl($keepId);
        $beforeRemove = $this->fetchSeoUrl($removeId);

        static::assertNotNull($beforeKeep);
        static::assertNotNull($beforeRemove);

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$route], '--dry-run' => true]);

        $display = $tester->getDisplay();
        static::assertStringContainsString('Duplicate groups found:', $display);
        static::assertStringContainsString('Redundant canonical entries to delete: 1', $display);

        $afterKeep = $this->fetchSeoUrl($keepId);
        $afterRemove = $this->fetchSeoUrl($removeId);

        static::assertNotNull($afterKeep);
        static::assertNotNull($afterRemove);

        static::assertSame($beforeKeep['sales_channel_id'], $afterKeep['sales_channel_id']);
        static::assertSame($beforeKeep['is_canonical'], $afterKeep['is_canonical']);
        static::assertSame($beforeRemove['is_canonical'], $afterRemove['is_canonical']);
        static::assertSame($beforeRemove['is_deleted'], $afterRemove['is_deleted']);
    }

    public function testRouteFilterLimitsScope(): void
    {
        $routeA = 'integration.seo.dedupe.case4.a';
        $routeB = 'integration.seo.dedupe.case4.b';
        $fkA = Uuid::randomHex();
        $fkB = Uuid::randomHex();

        [$keepA, $removeA] = $this->createDuplicateCanonicalGroup($routeA, $fkA, 'detail/a', 'slug-a', '2020-01-01 00:00:00.000', '2021-01-01 00:00:00.000');
        [$keepB, $removeB] = $this->createDuplicateCanonicalGroup($routeB, $fkB, 'detail/b', 'slug-b', '2020-01-01 00:00:00.000', '2021-01-01 00:00:00.000');

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$routeA], '--soft-delete' => true]);

        // Route A changed
        $rowKeepA = $this->fetchSeoUrl($keepA);
        $rowRemoveA = $this->fetchSeoUrl($removeA);
        static::assertNotNull($rowKeepA);
        static::assertNotNull($rowRemoveA);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowKeepA */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowRemoveA */
        static::assertNotNull($rowKeepA['sales_channel_id']);
        static::assertNull($rowRemoveA['is_canonical']);

        // Route B untouched
        $rowKeepB = $this->fetchSeoUrl($keepB);
        $rowRemoveB = $this->fetchSeoUrl($removeB);
        static::assertNotNull($rowKeepB);
        static::assertNotNull($rowRemoveB);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowKeepB */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowRemoveB */
        static::assertNotNull($rowKeepB['sales_channel_id']);
        static::assertSame('1', (string) $rowKeepB['is_canonical']);
        static::assertSame('1', (string) $rowRemoveB['is_canonical']);
        static::assertSame('0', (string) $rowRemoveB['is_deleted']);
    }

    public function testNonDefaultOnlySoftDelete(): void
    {
        $route = 'integration.seo.dedupe.case5';
        $fk = Uuid::randomHex();

        // Default language row
        $defaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($defaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelA),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/999',
            'seo_path_info' => 'awesome-999',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-01 00:00:00.000',
        ]);

        // Non-default duplicate
        $nonDefaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($nonDefaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelB),
            'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/999',
            'seo_path_info' => 'awesome-999',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-02 00:00:00.000',
        ]);

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$route], '--soft-delete' => true, '--non-default-only' => true]);

        $rowDefault = $this->fetchSeoUrl($defaultId);
        $rowNonDefault = $this->fetchSeoUrl($nonDefaultId);
        static::assertNotNull($rowDefault);
        static::assertNotNull($rowNonDefault);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowDefault */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowNonDefault */
        static::assertSame('1', (string) $rowDefault['is_canonical']);
        static::assertNull($rowNonDefault['is_canonical']);
        static::assertSame(1, (int) $rowNonDefault['is_deleted']);
    }

    public function testPreferDefaultKeeperKeepsDefaultLanguageEvenIfNewer(): void
    {
        $route = 'integration.seo.dedupe.prefer-default';
        $fk = Uuid::randomHex();

        // Newer default-language row
        $defaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($defaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelA),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/111',
            'seo_path_info' => 'awesome-111',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-02 00:00:00.000',
        ]);

        // Older non-default duplicate
        $nonDefaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($nonDefaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelB),
            'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/111',
            'seo_path_info' => 'awesome-111',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-01 00:00:00.000',
        ]);

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute([
            '--route' => [$route],
            '--soft-delete' => true,
            '--prefer-default-keeper' => true,
        ]);

        $rowDefault = $this->fetchSeoUrl($defaultId);
        $rowNonDefault = $this->fetchSeoUrl($nonDefaultId);
        static::assertNotNull($rowDefault);
        static::assertNotNull($rowNonDefault);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowDefault */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowNonDefault */
        static::assertSame('1', (string) $rowDefault['is_canonical']);
        static::assertNull($rowNonDefault['is_canonical']);
        static::assertSame(1, (int) $rowNonDefault['is_deleted']);
    }

    public function testWithoutPreferenceKeepsOldestEvenIfNonDefault(): void
    {
        $route = 'integration.seo.dedupe.keep-oldest-non-default';
        $fk = Uuid::randomHex();

        // Newer default-language row
        $defaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($defaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelA),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/222',
            'seo_path_info' => 'awesome-222',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-02 00:00:00.000',
        ]);

        // Older non-default duplicate
        $nonDefaultId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($nonDefaultId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelB),
            'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/222',
            'seo_path_info' => 'awesome-222',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-01 00:00:00.000',
        ]);

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute([
            '--route' => [$route],
            '--soft-delete' => true,
            // no --prefer-default-keeper on purpose
        ]);

        $rowDefault = $this->fetchSeoUrl($defaultId);
        $rowNonDefault = $this->fetchSeoUrl($nonDefaultId);
        static::assertNotNull($rowDefault);
        static::assertNotNull($rowNonDefault);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowDefault */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowNonDefault */
        static::assertNull($rowDefault['is_canonical'], 'Default-language newer row should be soft-deleted');
        static::assertSame(1, (int) $rowDefault['is_deleted']);
        static::assertSame('1', (string) $rowNonDefault['is_canonical'], 'Older non-default row should be kept');
    }

    public function testKeepsExistingGlobalAndSoftDeletesOthers(): void
    {
        $route = 'integration.seo.dedupe.case6';
        $fk = Uuid::randomHex();

        // Prepare a global canonical entry that should be kept
        $globalId = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($globalId),
            'sales_channel_id' => null,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/777',
            'seo_path_info' => 'awesome-777',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2021-01-01 00:00:00.000',
        ]);

        // Add two per-channel duplicates
        $idA = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($idA),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelA),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/777',
            'seo_path_info' => 'awesome-777',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2022-01-01 00:00:00.000',
        ]);

        $idB = Uuid::randomHex();
        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($idB),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelB),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($fk),
            'route_name' => $route,
            'path_info' => '/detail/777',
            'seo_path_info' => 'awesome-777',
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => '2023-01-01 00:00:00.000',
        ]);

        $tester = new CommandTester(static::getContainer()->get(SeoUrlDeduplicateCommand::class));
        $tester->execute(['--route' => [$route], '--soft-delete' => true]);

        $display = $tester->getDisplay();
        static::assertStringContainsString('Duplicate groups found:', $display);
        static::assertStringContainsString('Redundant canonical entries to delete: 2', $display);

        $global = $this->fetchSeoUrl($globalId);
        static::assertNotNull($global);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $global */
        static::assertNull($global['sales_channel_id']);
        static::assertSame('1', (string) $global['is_canonical']);

        $rowA = $this->fetchSeoUrl($idA);
        $rowB = $this->fetchSeoUrl($idB);
        static::assertNotNull($rowA);
        static::assertNotNull($rowB);
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowA */
        /** @var array{id: string, sales_channel_id: string|null, is_canonical: int|string|null, is_deleted: int|string} $rowB */
        static::assertNull($rowA['is_canonical']);
        static::assertNull($rowB['is_canonical']);
        static::assertSame('1', (string) $rowA['is_deleted']);
        static::assertSame('1', (string) $rowB['is_deleted']);
    }

    /**
     * @return array{0: string, 1: string} [keepId, removeId]
     */
    private function createDuplicateCanonicalGroup(string $route, string $foreignKey, string $pathInfo, string $seoPathInfo, string $createdAtKeep, string $createdAtRemove): array
    {
        $keepId = Uuid::randomHex();
        $removeId = Uuid::randomHex();

        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($keepId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelA),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($foreignKey),
            'route_name' => $route,
            'path_info' => '/' . ltrim($pathInfo, '/'),
            'seo_path_info' => $seoPathInfo,
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => $createdAtKeep,
        ]);

        $this->connection->insert('seo_url', [
            'id' => Uuid::fromHexToBytes($removeId),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelB),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::fromHexToBytes($foreignKey),
            'route_name' => $route,
            'path_info' => '/' . ltrim($pathInfo, '/'),
            'seo_path_info' => $seoPathInfo,
            'is_canonical' => 1,
            'is_modified' => 0,
            'is_deleted' => 0,
            'created_at' => $createdAtRemove,
        ]);

        return [$keepId, $removeId];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSeoUrl(string $id): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) as id, LOWER(HEX(sales_channel_id)) as sales_channel_id, is_canonical, is_deleted FROM seo_url WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        ) ?: null;
    }
}
