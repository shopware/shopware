<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
trait MigrationTestTrait
{
    /**
     * @before
     */
    public function startTransaction(): void
    {
        KernelLifecycleManager::getConnection()->beginTransaction();
    }

    /**
     * @after
     */
    public function rollbackTransaction(): void
    {
        KernelLifecycleManager::getConnection()->rollBack();
    }
}
