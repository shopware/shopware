<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DebugStackTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExecuteQueryWriteCausesExceptionInTestEnv(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        static::expectExceptionMessage('Write operations are not supported when using executeQuery, use executeStatement instead.');
        $connection->executeQuery('CREATE TABLE `test` (
            `id` BINARY(16) NOT NULL PRIMARY KEY
        )');

        $connection->executeUpdate('DROP TABLE `test`;');
    }
}
