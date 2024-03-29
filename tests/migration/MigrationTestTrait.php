<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

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
}
