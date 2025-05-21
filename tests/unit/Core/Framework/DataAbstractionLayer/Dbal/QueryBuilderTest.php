<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
class QueryBuilderTest extends TestCase
{
    private Connection&MockObject $connection;

    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->queryBuilder = new QueryBuilder($this->connection);
    }

    public function testSetAndGetComment(): void
    {
        // Initial value should be null
        static::assertNull($this->queryBuilder->getComment());

        // Test setting a comment
        $this->queryBuilder->setComment('TEST_COMMENT');
        static::assertSame('TEST_COMMENT', $this->queryBuilder->getComment());

        // Test setting null
        $this->queryBuilder->setComment(null);
        static::assertNull($this->queryBuilder->getComment());
    }

    public function testGetSQLWithComment(): void
    {
        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder->select('*')->from('test_table');

        // Set a comment
        $queryBuilder->setComment('+ MAX_EXECUTION_TIME(1000)');

        // Verify the comment is stored correctly
        static::assertSame('+ MAX_EXECUTION_TIME(1000)', $queryBuilder->getComment());

        // Get the SQL and verify the comment is applied correctly to SELECT
        $sql = $queryBuilder->getSQL();
        static::assertStringContainsString('SELECT /*+ MAX_EXECUTION_TIME(1000)*/ ', $sql);
    }

    public function testGetSQLWithTitle(): void
    {
        // Set up our QueryBuilder
        $queryBuilder = new QueryBuilder($this->connection);

        // Set a title
        $queryBuilder->setTitle('Test Query');

        // Verify the title is stored correctly
        static::assertSame('Test Query', $queryBuilder->getTitle());

        // Create a SELECT statement and check that the title is in the SQL
        $queryBuilder->select('*')->from('test_table');
        $sql = $queryBuilder->getSQL();

        // Verify the title is applied correctly
        static::assertStringContainsString('# Test Query', $sql);
    }

    public function testGetSQLWithCommentAndTitle(): void
    {
        $queryBuilder = new QueryBuilder($this->connection);
        // Setup the builder
        $queryBuilder->select('*')->from('test_table');

        // Set both title and comment
        $queryBuilder->setTitle('Test Query');
        $queryBuilder->setComment('+ MAX_EXECUTION_TIME(1000)');

        // Verify properties are stored correctly
        static::assertSame('Test Query', $queryBuilder->getTitle());
        static::assertSame('+ MAX_EXECUTION_TIME(1000)', $queryBuilder->getComment());

        // Get the SQL and verify both transformations are applied correctly
        $sql = $queryBuilder->getSQL();
        static::assertStringContainsString('# Test Query', $sql);
        static::assertStringContainsString('SELECT /*+ MAX_EXECUTION_TIME(1000)*/ ', $sql);
    }

    public function testCommentOnlyAppliedToSelectStatements(): void
    {
        // Create a QueryBuilder with an UPDATE statement
        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder->update('test_table')->set('column', 'value');
        $queryBuilder->setComment('TEST_COMMENT');

        // Verify the comment is stored
        static::assertSame('TEST_COMMENT', $queryBuilder->getComment());

        // Get the SQL for the UPDATE statement
        $sql = $queryBuilder->getSQL();

        // Verify that comment would NOT be added to non-SELECT statements
        static::assertStringNotContainsString('/*TEST_COMMENT*/', $sql);
    }

    #[DataProvider('queryTimeoutProvider')]
    public function testSetQueryTimeout(AbstractPlatform $platform, int $timeout, ?string $expectedComment): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $queryBuilder = new QueryBuilder($connection);

        // Call the method to test
        $result = $queryBuilder->setQueryTimeout($timeout);

        // Verify the comment is set correctly
        static::assertSame($expectedComment, $queryBuilder->getComment());

        // Verify the method returns $this for method chaining
        static::assertSame($queryBuilder, $result);
    }

    public static function queryTimeoutProvider(): \Generator
    {
        yield 'MySQL Platform' => [
            new MySQLPlatform(),
            1000,
            '+ MAX_EXECUTION_TIME(1000)',
        ];
        yield 'MariaDB Platform' => [
            new MariaDB1060Platform(),
            1000,
            '+ MAX_STATEMENT_TIME(1)',
        ];
        yield 'Other Platform' => [
            new PostgreSQLPlatform(),
            1000,
            null, // No comment should be set for unsupported platforms
        ];
    }

    public function testAddAndGetState(): void
    {
        // Initial state should be empty
        static::assertEmpty($this->queryBuilder->getStates());

        // Test adding a state
        $this->queryBuilder->addState('test_state');
        static::assertTrue($this->queryBuilder->hasState('test_state'));
        static::assertSame(['test_state' => 'test_state'], $this->queryBuilder->getStates());

        // Test removing a state
        $this->queryBuilder->removeState('test_state');
        static::assertFalse($this->queryBuilder->hasState('test_state'));
        static::assertEmpty($this->queryBuilder->getStates());
    }

    public function testTranslationJoins(): void
    {
        // Mock the connection behavior to handle getSQL call properly
        $mockConnection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create real QueryBuilder instances with our mock connection
        $translationBuilder = new QueryBuilder($mockConnection);
        $translationBuilder->select('translation.*')
            ->from('translation_table', 'translation');

        $queryBuilder = new QueryBuilder($mockConnection);
        $queryBuilder->select('entity.*')
            ->from('entity_table', 'entity');

        // Add the translation join
        $queryBuilder->addTranslationJoin(
            'entity',
            'translation',
            $translationBuilder,
            'entity.id = translation.entity_id'
        );

        // Verify we can retrieve the translation builder
        $returnedBuilder = $queryBuilder->getTranslationQueryBuilder('translation');
        static::assertSame($translationBuilder, $returnedBuilder);
    }

    public function testGetNonExistentTranslationQueryBuilder(): void
    {
        // Test getting a non-existent translation query builder
        $returnedBuilder = $this->queryBuilder->getTranslationQueryBuilder('non_existent');
        static::assertNull($returnedBuilder);
    }

    public function testSelectPartsTracking(): void
    {
        // Test tracking of select parts
        $this->queryBuilder->select('column1', 'column2');
        static::assertSame(['column1', 'column2'], $this->queryBuilder->getSelectParts());

        // Test tracking of added select parts
        $this->queryBuilder->addSelect('column3', 'column4');
        static::assertSame(['column1', 'column2', 'column3', 'column4'], $this->queryBuilder->getSelectParts());
    }

    public function testOrderByPartsTracking(): void
    {
        // Test tracking of order by parts
        $this->queryBuilder->orderBy('column1', 'ASC');
        static::assertSame(['column1 ASC'], $this->queryBuilder->getOrderByParts());

        // Test tracking of added order by parts
        $this->queryBuilder->addOrderBy('column2', 'DESC');
        static::assertSame(['column1 ASC', 'column2 DESC'], $this->queryBuilder->getOrderByParts());
    }
}
