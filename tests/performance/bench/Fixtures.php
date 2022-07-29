<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\FixtureLoader;

/**
 * @internal - only for performance benchmarks
 */
class Fixtures
{
    private static ?IdsCollection $ids = null;

    private static ?SalesChannelContext $context = null;

    public function load(): void
    {
        $loader = new FixtureLoader(
            KernelLifecycleManager::getKernel()->getContainer()->get(EntityWriter::class)
        );

        $ids = $loader->load(__DIR__ . '/data.json');

        $sql = '
CREATE TABLE IF NOT EXISTS `php_bench` (
  `key` varchar(50) NOT NULL,
  `ids` longblob NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->executeStatement($sql);

        $sql = 'REPLACE INTO php_bench (`key`, `ids`) VALUES (:key, :ids)';

        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->executeStatement($sql, ['key' => 'ids', 'ids' => \serialize($ids)]);
    }

    public static function getIds(): IdsCollection
    {
        if (self::$ids === null) {
            $ids = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class)
                ->fetchOne('SELECT ids FROM php_bench WHERE `key` = :key', ['key' => 'ids']);

            self::$ids = \unserialize($ids);
        }

        return self::$ids;
    }

    public static function context(): SalesChannelContext
    {
        if (self::$context === null) {
            self::$context = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), self::getIds()->get('sales-channel'));
        }

        return self::$context;
    }
}
