<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
trait MigrationTestTrait
{
    #[Before]
    public function startTransaction(): void
    {
        KernelLifecycleManager::getConnection()->beginTransaction();
    }

    #[After]
    public function rollbackTransaction(): void
    {
        KernelLifecycleManager::getConnection()->rollBack();
    }

    protected function fetchLanguageId(Connection $connection, string $code): ?string
    {
        return $connection->fetchOne(
            'SELECT `language`.`id`
             FROM `language`
                 INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
             WHERE `locale`.`code` = :code
             ORDER BY `language`.`created_at` ASC
             LIMIT 1',
            ['code' => $code]
        ) ?: null;
    }
}
