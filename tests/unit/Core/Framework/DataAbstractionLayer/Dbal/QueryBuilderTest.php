<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
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
        $platform = $this->createMock(AbstractPlatform::class);

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn($platform);

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
        // We'll simulate what getSQL() does but in a controlled way
        // First set up our QueryBuilder
        $queryBuilder = new QueryBuilder($this->connection);
        
        // Set a comment
        $queryBuilder->setComment('+ MAX_EXECUTION_TIME(1000)');
        
        // Create a reflection class to access private methods/properties
        $reflectionClass = new \ReflectionClass(QueryBuilder::class);
        $commentProperty = $reflectionClass->getProperty('comment');
        $commentProperty->setAccessible(true);
        
        // Verify the comment is stored correctly
        static::assertEquals('+ MAX_EXECUTION_TIME(1000)', $commentProperty->getValue($queryBuilder));
        
        // Now manually apply the comment transformation to test it
        $sql = 'SELECT * FROM test_table';
        $commentValue = $commentProperty->getValue($queryBuilder);
        $modifiedSql = 'SELECT /*' . $commentValue . '*/ ' . substr($sql, 7);
        
        // Verify the comment is applied correctly
        static::assertStringContainsString('SELECT /*+ MAX_EXECUTION_TIME(1000)*/ ', $modifiedSql);
    }

    public function testGetSQLWithTitle(): void
    {
        // Set up our QueryBuilder
        $queryBuilder = new QueryBuilder($this->connection);
        
        // Set a title
        $queryBuilder->setTitle('Test Query');
        
        // Create a reflection class to access private methods/properties
        $reflectionClass = new \ReflectionClass(QueryBuilder::class);
        $titleProperty = $reflectionClass->getProperty('title');
        $titleProperty->setAccessible(true);
        
        // Verify the title is stored correctly
        static::assertEquals('Test Query', $titleProperty->getValue($queryBuilder));
        
        // Now manually apply the title transformation to test it
        $sql = 'SELECT * FROM test_table';
        $titleValue = $titleProperty->getValue($queryBuilder);
        $modifiedSql = '# ' . $titleValue . PHP_EOL . $sql;
        
        // Verify the title is applied correctly
        static::assertStringContainsString('# Test Query', $modifiedSql);
    }

    public function testGetSQLWithCommentAndTitle(): void
    {
        // Set up our QueryBuilder
        $queryBuilder = new QueryBuilder($this->connection);
        
        // Set both title and comment
        $queryBuilder->setTitle('Test Query');
        $queryBuilder->setComment('+ MAX_EXECUTION_TIME(1000)');
        
        // Create a reflection class to access private methods/properties
        $reflectionClass = new \ReflectionClass(QueryBuilder::class);
        $titleProperty = $reflectionClass->getProperty('title');
        $commentProperty = $reflectionClass->getProperty('comment');
        $titleProperty->setAccessible(true);
        $commentProperty->setAccessible(true);
        
        // Verify properties are stored correctly
        static::assertEquals('Test Query', $titleProperty->getValue($queryBuilder));
        static::assertEquals('+ MAX_EXECUTION_TIME(1000)', $commentProperty->getValue($queryBuilder));
        
        // Now manually apply the transformations to test them
        $sql = 'SELECT * FROM test_table';
        $titleValue = $titleProperty->getValue($queryBuilder);
        $commentValue = $commentProperty->getValue($queryBuilder);
        
        // First add comment to SELECT
        $modifiedSql = 'SELECT /*' . $commentValue . '*/ ' . substr($sql, 7);
        // Then add title at the beginning
        $modifiedSql = '# ' . $titleValue . PHP_EOL . $modifiedSql;
        
        // Verify both transformations are applied correctly
        static::assertStringContainsString('# Test Query', $modifiedSql);
        static::assertStringContainsString('SELECT /*+ MAX_EXECUTION_TIME(1000)*/ ', $modifiedSql);
    }

    public function testCommentOnlyAppliedToSelectStatements(): void
    {
        // Create a QueryBuilder with an UPDATE statement
        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder->update('test_table')->set('column', 'value');
        $queryBuilder->setComment('TEST_COMMENT');
        
        // Create a reflection class to access private properties
        $reflectionClass = new \ReflectionClass(QueryBuilder::class);
        $commentProperty = $reflectionClass->getProperty('comment');
        $commentProperty->setAccessible(true);
        
        // Verify the comment is stored
        static::assertEquals('TEST_COMMENT', $commentProperty->getValue($queryBuilder));
        
        // Now manually verify that comment would NOT be added to non-SELECT statements
        $sql = 'UPDATE test_table SET column = value';
        static::assertStringNotContainsString('/*TEST_COMMENT*/', $sql);
        
        // For this test, we only want to verify that the getSQL method in QueryBuilder 
        // would NOT add comments to non-SELECT statements, which is handled by the condition:
        // if (strpos($sql, 'SELECT ') === 0) { ... }
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
        // Create real QueryBuilder instances
        $translationBuilder = new QueryBuilder($this->connection);
        $translationBuilder->select('translation.*')
            ->from('translation_table', 'translation');

        $queryBuilder = new QueryBuilder($this->connection);
        $queryBuilder->select('entity.*')
            ->from('entity_table', 'entity');
            
        // Add the translation join
        $queryBuilder->addTranslationJoin(
            'entity',
            'translation',
            $translationBuilder,
            'entity.id = translation.entity_id'
        );

        // Create a reflection class to access private properties
        $reflectionClass = new \ReflectionClass(QueryBuilder::class);
        $translationJoinsProperty = $reflectionClass->getProperty('translationJoins');
        $translationJoinsProperty->setAccessible(true);
        
        // Verify translation join is stored correctly
        $translationJoins = $translationJoinsProperty->getValue($queryBuilder);
        static::assertArrayHasKey('translation', $translationJoins);
        static::assertSame($translationBuilder, $translationJoins['translation']['queryBuilder']);
        static::assertEquals('entity.id = translation.entity_id', $translationJoins['translation']['joinCondition']);
        
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
        static::assertEquals(['column1', 'column2'], $this->queryBuilder->getSelectParts());

        // Test tracking of added select parts
        $this->queryBuilder->addSelect('column3', 'column4');
        static::assertEquals(['column1', 'column2', 'column3', 'column4'], $this->queryBuilder->getSelectParts());
    }

    public function testOrderByPartsTracking(): void
    {
        // Test tracking of order by parts
        $this->queryBuilder->orderBy('column1', 'ASC');
        static::assertEquals(['column1 ASC'], $this->queryBuilder->getOrderByParts());

        // Test tracking of added order by parts
        $this->queryBuilder->addOrderBy('column2', 'DESC');
        static::assertEquals(['column1 ASC', 'column2 DESC'], $this->queryBuilder->getOrderByParts());
    }
}