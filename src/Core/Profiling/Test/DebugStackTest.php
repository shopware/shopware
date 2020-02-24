<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DebugStackTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @group legacy
     * @expectedDeprecation Write operations are not supported when using executeQuery.
     */
    public function testExecuteQueryWriteCausesDeprecationWarningInNonTestEnv(): void
    {
        putenv('APP_ENV=not_test');
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeQuery('CREATE TABLE `test` (
            `id` BINARY(16) NOT NULL PRIMARY KEY
        )');

        $connection->executeUpdate('DROP TABLE `test`;');
        putenv('APP_ENV=test');
    }

    public function testExecuteQueryWriteCausesExceptionInTestEnv(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        static::expectExceptionMessage('Write operations are not supported when using executeQuery.');
        $connection->executeQuery('CREATE TABLE `test` (
            `id` BINARY(16) NOT NULL PRIMARY KEY
        )');

        $connection->executeUpdate('DROP TABLE `test`;');
    }
}
