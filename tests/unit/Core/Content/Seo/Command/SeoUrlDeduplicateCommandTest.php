<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Command\SeoUrlDeduplicateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlDeduplicateCommand::class)]
class SeoUrlDeduplicateCommandTest extends TestCase
{
    public function testDryRunSkipsWrites(): void
    {
        $conn = $this->createMock(Connection::class);

        $rows = [
            [
                'id' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2020-01-01 00:00:00.000',
            ],
            [
                'id' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'foreignKey' => Uuid::randomHex(),
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2021-01-01 00:00:00.000',
            ],
        ];

        $conn->method('fetchAllAssociative')->willReturn($rows);

        // transactional should invoke the closure directly
        $conn->method('transactional')->willReturnCallback(function (\Closure $fn) use ($conn) {
            return $fn($conn);
        });

        // No writes during dry-run
        $conn->expects($this->never())->method('executeStatement');

        $tester = new CommandTester(new SeoUrlDeduplicateCommand($conn));
        $exit = $tester->execute([
            '--route' => ['frontend.detail.page'],
            '--dry-run' => true,
        ]);

        static::assertSame(0, $exit);
        static::assertStringContainsString('Duplicate groups found:', $tester->getDisplay());
        static::assertStringContainsString('Redundant canonical entries to delete:', $tester->getDisplay());
    }

    public function testSoftDeleteRemovesDuplicatesWithoutPromotion(): void
    {
        $conn = $this->createMock(Connection::class);

        $keepId = Uuid::randomHex();
        $removeId = Uuid::randomHex();
        $lang = Uuid::randomHex();
        $fk = Uuid::randomHex();

        $rows = [
            [
                'id' => $keepId,
                'languageId' => $lang,
                'foreignKey' => $fk,
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2020-01-01 00:00:00.000',
            ],
            [
                'id' => $removeId,
                'languageId' => $lang,
                'foreignKey' => $fk,
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2021-01-01 00:00:00.000',
            ],
        ];

        $conn->method('fetchAllAssociative')->willReturn($rows);
        $conn->method('transactional')->willReturnCallback(function (\Closure $fn) use ($conn) {
            return $fn($conn);
        });

        $softDeleted = false;

        $conn->method('executeStatement')->willReturnCallback(function (string $sql, array $params) use (&$softDeleted, $removeId): int {
            if (str_starts_with($sql, 'UPDATE seo_url SET is_canonical = NULL') && isset($params['ids'])) {
                $softDeleted = $this->idsContain($params['ids'], [$removeId]);

                return 1;
            }

            return 0;
        });

        $tester = new CommandTester(new SeoUrlDeduplicateCommand($conn));
        $exit = $tester->execute([
            '--route' => ['frontend.detail.page'],
            '--soft-delete' => true,
        ]);

        static::assertSame(0, $exit);
        static::assertTrue($softDeleted, 'Expected duplicate to be soft-deleted');
    }

    public function testHardDeleteRemovesDuplicates(): void
    {
        $conn = $this->createMock(Connection::class);

        $keepId = Uuid::randomHex();
        $removeId = Uuid::randomHex();
        $lang = Uuid::randomHex();
        $fk = Uuid::randomHex();

        $rows = [
            [
                'id' => $keepId,
                'languageId' => $lang,
                'foreignKey' => $fk,
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2020-01-01 00:00:00.000',
            ],
            [
                'id' => $removeId,
                'languageId' => $lang,
                'foreignKey' => $fk,
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2021-01-01 00:00:00.000',
            ],
        ];

        $conn->method('fetchAllAssociative')->willReturn($rows);
        $conn->method('transactional')->willReturnCallback(function (\Closure $fn) use ($conn) {
            return $fn($conn);
        });

        $deleted = false;

        $conn->method('executeStatement')->willReturnCallback(function (string $sql, array $params) use (&$deleted, $removeId): int {
            if (str_starts_with($sql, 'DELETE FROM seo_url') && isset($params['ids'])) {
                $deleted = $this->idsContain($params['ids'], [$removeId]);

                return 1;
            }

            return 0;
        });

        $tester = new CommandTester(new SeoUrlDeduplicateCommand($conn));
        $exit = $tester->execute([
            '--route' => ['frontend.detail.page'],
            '--hard-delete' => true,
        ]);

        static::assertSame(0, $exit);
        static::assertTrue($deleted, 'Expected duplicate to be deleted');
    }

    public function testPreferDefaultKeeperKeepsDefaultLanguage(): void
    {
        $conn = $this->createMock(Connection::class);

        $defaultLang = Uuid::randomHex(); // will be overwritten by Defaults in grouping, but keep structure
        $defaultId = Uuid::randomHex();
        $deId = Uuid::randomHex();

        $rows = [
            [
                'id' => $deId,
                'languageId' => Uuid::randomHex(), // simulate non-default
                'foreignKey' => Uuid::randomHex(),
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2020-01-01 00:00:00.000',
            ],
            [
                'id' => $defaultId,
                'languageId' => \strtolower(\Shopware\Core\Defaults::LANGUAGE_SYSTEM),
                'foreignKey' => Uuid::randomHex(),
                'routeName' => 'frontend.detail.page',
                'seoPathInfo' => 'awesome',
                'pathInfo' => '/detail/1',
                'isGlobal' => 0,
                'createdAt' => '2021-01-01 00:00:00.000',
            ],
        ];

        $conn->method('fetchAllAssociative')->willReturn($rows);
        $conn->method('transactional')->willReturnCallback(function (\Closure $fn) use ($conn) {
            return $fn($conn);
        });

        $softDeleted = false;
        $conn->method('executeStatement')->willReturnCallback(function (string $sql, array $params) use (&$softDeleted, $deId): int {
            if (str_starts_with($sql, 'UPDATE seo_url SET is_canonical = NULL') && isset($params['ids'])) {
                $softDeleted = $this->idsContain($params['ids'], [$deId]);

                return 1;
            }

            return 0;
        });

        $tester = new CommandTester(new SeoUrlDeduplicateCommand($conn));
        $exit = $tester->execute([
            '--route' => ['frontend.detail.page'],
            '--soft-delete' => true,
            '--prefer-default-keeper' => true,
        ]);

        static::assertSame(0, $exit);
        static::assertTrue($softDeleted, 'Expected non-default duplicate to be soft-deleted');
    }

    /**
     * @param list<string> $binaryIds
     * @param list<string> $hexIds
     */
    private function idsContain(array $binaryIds, array $hexIds): bool
    {
        $expected = array_map(static fn (string $h): string => Uuid::fromHexToBytes($h), $hexIds);
        foreach ($expected as $e) {
            $found = false;
            foreach ($binaryIds as $b) {
                if ($b === $e) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }
}
