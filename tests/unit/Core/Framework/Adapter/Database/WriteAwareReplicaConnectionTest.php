<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\WriteAwareReplicaConnection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteAwareReplicaConnection::class)]
class WriteAwareReplicaConnectionTest extends TestCase
{
    #[DataProvider('writeStatementProvider')]
    public function testWriteStatementsAreDetected(string $sql, bool $expected): void
    {
        static::assertSame($expected, WriteAwareReplicaConnection::isWriteStatement($sql));
    }

    public static function writeStatementProvider(): \Generator
    {
        yield 'select stays a read' => ['SELECT id FROM product', false];
        yield 'show stays a read' => ['SHOW TABLES', false];
        yield 'lower case select stays a read' => ["\n  select 1", false];
        yield 'update' => ['UPDATE product SET active = 1', true];
        yield 'lower case insert' => ['insert into product (id) values (1)', true];
        yield 'delete' => ['DELETE FROM product WHERE id = 1', true];
        yield 'replace' => ['REPLACE INTO product (id) VALUES (1)', true];
        yield 'truncate' => ['TRUNCATE product', true];
        yield 'alter' => ['ALTER TABLE product ADD COLUMN foo INT', true];
        yield 'create' => ['CREATE TABLE foo (id INT)', true];
        yield 'drop' => ['DROP TABLE foo', true];
        yield 'rename' => ['RENAME TABLE foo TO bar', true];
        yield 'load data' => ['LOAD DATA INFILE \'x\' INTO TABLE foo', true];
        yield 'write behind a block comment' => ['/* audit */ UPDATE product SET active = 1', true];
        yield 'write behind a line comment' => ["-- audit\nDELETE FROM product", true];
        yield 'write behind a hash comment' => ["# audit\nINSERT INTO product (id) VALUES (1)", true];
        yield 'write behind parentheses' => ['(INSERT INTO product (id) VALUES (1))', true];
        yield 'read behind a comment stays a read' => ['/* UPDATE in a comment */ SELECT 1', false];
        yield 'cte read stays a read' => ['WITH ids AS (SELECT id FROM product) SELECT * FROM ids', false];
        yield 'cte update' => ['WITH ids AS (SELECT id FROM product) UPDATE product SET active = 0 WHERE id IN (SELECT id FROM ids)', true];
        yield 'cte delete' => ['WITH ids AS (SELECT id FROM product) DELETE FROM product WHERE id IN (SELECT id FROM ids)', true];
        yield 'locking read for update' => ['SELECT id FROM product WHERE id = 1 FOR UPDATE', true];
        yield 'locking read for share' => ['SELECT id FROM product WHERE id = 1 FOR SHARE', true];
        yield 'locking read in share mode' => ['SELECT id FROM product LOCK IN SHARE MODE', true];
        yield 'column named for_update stays a read' => ['SELECT for_update FROM product', false];
    }

    public function testReadsStayOnTheReplica(): void
    {
        $connection = $this->createConnection();

        $connection->executeQuery('SELECT 1');

        static::assertFalse($connection->isConnectedToPrimary());
    }

    public function testAWriteThroughExecuteQueryMovesToThePrimary(): void
    {
        $connection = $this->createConnection();
        $connection->executeQuery('UPDATE product SET active = 1'); // @phpstan-ignore shopware.noExecuteQuery (the wrapper exists to catch exactly this call shape)

        static::assertTrue($connection->isConnectedToPrimary());
    }

    public function testReadsAfterAWriteStayOnThePrimary(): void
    {
        $connection = $this->createConnection();

        $connection->executeQuery('SELECT 1');
        static::assertFalse($connection->isConnectedToPrimary());
        $connection->executeQuery('DELETE FROM product'); // @phpstan-ignore shopware.noExecuteQuery (the wrapper exists to catch exactly this call shape)
        static::assertTrue($connection->isConnectedToPrimary());

        $connection->executeQuery('SELECT 1');
        static::assertTrue($connection->isConnectedToPrimary(), 'a read after a write must see the written data');
    }

    public function testAWriteWithParametersMovesToThePrimary(): void
    {
        $connection = $this->createConnection();
        $connection->executeQuery('UPDATE product SET active = ? WHERE id = ?', [1, 'id']); // @phpstan-ignore shopware.noExecuteQuery (the wrapper exists to catch exactly this call shape)

        static::assertTrue($connection->isConnectedToPrimary());
    }

    private function createConnection(): WriteAwareReplicaConnection
    {
        $result = static::createStub(DriverResult::class);

        $statement = static::createStub(Driver\Statement::class);
        $statement->method('execute')->willReturn($result);

        $primary = static::createStub(DriverConnection::class);
        $primary->method('query')->willReturn($result);
        $primary->method('prepare')->willReturn($statement);

        $replica = static::createStub(DriverConnection::class);
        $replica->method('query')->willReturn($result);
        $replica->method('prepare')->willReturn($statement);

        $driver = static::createStub(Driver::class);
        $driver->method('connect')->willReturnCallback(static fn (array $params): DriverConnection => $params['host'] === 'primary' ? $primary : $replica);

        return new WriteAwareReplicaConnection([
            'primary' => ['host' => 'primary'],
            'replica' => [['host' => 'replica']],
            'keepReplica' => true,
        ], $driver);
    }
}
