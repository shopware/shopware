<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;

trait MigrationTestBehaviour
{
    use KernelTestBehaviour;

    /**
     * @before
     */
    public function skipDestructiveMigration(): void
    {
        $wasExecutedDestructive = (bool) $this->getContainer()
            ->get(Connection::class)
            ->executeStatement(
                $this->getSql(),
                ['class' => $this->getMigrationClass()]
            );

        if ($wasExecutedDestructive) {
            static::markTestSkipped('Test was skipped, as the related migration was executed destructively before.');
        }
    }

    // implement in migration test to return the migration's FQCN
    abstract protected function getMigrationClass(): string;

    private function getSql(): string
    {
        return <<<'SQL'
SELECT * FROM `migration` WHERE `class` = :class AND `update_destructive` IS NOT NULL
SQL;
    }
}
