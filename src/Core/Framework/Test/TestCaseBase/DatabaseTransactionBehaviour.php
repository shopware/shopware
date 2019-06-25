<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
        $this->getContainer()
            ->get(Connection::class)
            ->beginTransaction();
    }

    /**
     * @after
     */
    public function stopTransactionAfter(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->rollBack();
    }

    abstract protected function getContainer(): ContainerInterface;
}
