<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\FixtureLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal - only for performance benchmarks
 */
class Fixtures
{
    private static ?IdsCollection $ids = null;

    private static ?SalesChannelContext $context = null;

    public function load(string $data): void
    {
        $content = $data;
        if (is_file($data)) {
            $content = (string) \file_get_contents($data);
        }
        $container = KernelLifecycleManager::getKernel()->getContainer();
        $loader = new FixtureLoader($container);
        $ids = $loader->load($content, self::$ids);

        $sql = '
CREATE TABLE IF NOT EXISTS `php_bench` (
  `key` varchar(50) NOT NULL,
  `ids` longblob NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        $container
            ->get(Connection::class)
            ->executeStatement($sql);

        $sql = 'REPLACE INTO php_bench (`key`, `ids`) VALUES (:key, :ids)';

        $container
            ->get(Connection::class)
            ->executeStatement($sql, ['key' => 'ids', 'ids' => \serialize($ids)]);
    }

    public static function getIds(): IdsCollection
    {
        if (!self::$ids instanceof IdsCollection) {
            $ids = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class)
                ->fetchOne('SELECT ids FROM php_bench WHERE `key` = :key', ['key' => 'ids']);

            self::$ids = \unserialize($ids);
        }

        return self::$ids;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function context(array $options = []): SalesChannelContext
    {
        if (!self::$context instanceof SalesChannelContext) {
            self::$context = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), self::getIds()->get('sales-channel'), $options);
        }

        return self::$context;
    }
}
