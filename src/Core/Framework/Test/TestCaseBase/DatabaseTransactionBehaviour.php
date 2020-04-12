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
        /** @var Connection $connection */
        $connection = $this->getContainer()
            ->get(Connection::class);

        self::assertEquals(
            1,
            $connection->getTransactionNestingLevel(),
            'Too many Nesting Levels.
            Probably one transaction was not closed properly.
            This may affect following Tests in an unpredictable manner!
            Current nesting level: "' . $connection->getTransactionNestingLevel() . '".'
        );

        $connection->rollBack();
    }

    abstract protected function getContainer(): ContainerInterface;
}
