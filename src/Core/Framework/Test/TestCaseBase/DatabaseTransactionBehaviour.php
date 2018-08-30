<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;

/**
 * Use if your test should be wrapped in a transaction
 */
trait DatabaseTransactionBehaviour
{
    /**
     * @before
     */
    public function startTransactionBefore(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->beginTransaction();
    }

    /**
     * @after
     */
    public function stopTransactionAfter(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class)
            ->rollback();
    }
}
