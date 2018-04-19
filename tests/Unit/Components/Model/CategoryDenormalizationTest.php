<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Unit\Components\Model;

use PHPUnit\DbUnit\Database\DefaultConnection;
use PHPUnit\DbUnit\DataSet\IDataSet;
use PHPUnit\DbUnit\DataSet\ReplacementDataSet;
use PHPUnit\DbUnit\TestCase;
use Shopware\Components\Model\CategoryDenormalization;

class PDOMock extends \PDO
{
    public function __construct()
    {
    }
}

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryDenormalizationTest extends TestCase
{
    /**
     * @var CategoryDenormalization
     */
    private $component;

    /**
     * @var \PDO
     */
    private $conn;

    protected function setUp()
    {
        if (!extension_loaded('sqlite3')) {
            $this->markTestSkipped(
                'The Sqlite3 extension is not available.'
            );

            parent::setUp();

            return;
        }

        try {
            $conn = new \PDO('sqlite::memory:');
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->markTestSkipped(
                'Could not create sqlite connection, got error:  ' . $e->getMessage()
            );
        }

        $schemaSql = file_get_contents(__DIR__ . '/_CategoryDenormalization/schema.sql');
        $conn->exec($schemaSql);

        $this->conn = $conn;
        $this->component = new CategoryDenormalization($conn);

        parent::setUp();
    }

    /**
     * @return DefaultConnection
     */
    public function getConnection()
    {
        if (!extension_loaded('sqlite3')) {
            return null;
        }

        return $this->createDefaultDBConnection($this->conn, ':memory:');
    }

    /**
     * 2. German
     *   4. Genusswelten
     *     5. Getränke
     * 3. English
     *   6. World of food
     *     7. Spirits
     *
     * @return IDataSet
     */
    public function getDataSet()
    {
        $dataset = $this->createFlatXMLDataSet(__DIR__ . '/_CategoryDenormalization/category-seed.xml');

        $dataset = new ReplacementDataSet($dataset);
        $dataset->addFullReplacement('##NULL##', null);

        return $dataset;
    }

    /**
     * @covers \Shopware\Components\Model\CategoryDenormalization::setConnection
     * @covers \Shopware\Components\Model\CategoryDenormalization::getConnection
     */
    public function testSetConnection()
    {
        $pdo = $this->createMock(PDOMock::class);

        $this->component->setConnection($pdo);

        $this->assertSame($pdo, $this->component->getConnection());
    }

    /**
     * @covers \Shopware\Components\Model\CategoryDenormalization::getParentCategoryIds
     */
    public function testGetParentCategoryIdsReturnsArrayWithCategoryIds()
    {
        $expectedResult = [5, 4, 2];

        $result = $this->component->getParentCategoryIds(5);

        $this->assertEquals($expectedResult, $result);
    }

    public function testRebuildAssignment()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('s_articles_categories_ro'));

        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');

        $result = $this->component->rebuildAllAssignmentsCount();
        // We have 2 rows in s_articles_categories
        $this->assertEquals(2, $result);

        // Six rows in s_articles_categories_ro have to be created
        $affectedRows = $this->component->rebuildAllAssignments();
        $this->assertEquals(6, $affectedRows);
        $this->assertEquals(6, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testAddAssignment()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('s_articles_categories_ro'));

        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');
        $this->component->addAssignment(1, 5);

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');
        $this->component->addAssignment(1, 7);

        $this->assertEquals(6, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testRemoveAssignment()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('s_articles_categories_ro'));

        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');
        $this->component->addAssignment(1, 5);

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');
        $this->component->addAssignment(1, 7);

        $this->assertEquals(6, $this->getConnection()->getRowCount('s_articles_categories_ro'));

        $this->conn->exec('DELETE FROM s_articles_categories WHERE articleID = 1 AND categoryID = 5');
        $this->component->removeAssignment(1, 5);

        $this->assertEquals(3, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testRebuildCategoryPath()
    {
        $result = $this->component->rebuildCategoryPathCount();

        // We have 6 row in our testdataset
        $this->assertEquals(6, $result);

        // 4 Rows are relevant
        $affectedRows = $this->component->rebuildCategoryPath();
        $this->assertEquals(4, $affectedRows);

        $expectedResult = [
            ['id' => '4', 'path' => '|2|'],
            ['id' => '5', 'path' => '|4|2|'],
            ['id' => '6', 'path' => '|3|'],
            ['id' => '7', 'path' => '|6|3|'],
        ];

        $result = $this->conn->query('SELECT id, path FROM s_categories WHERE path IS NOT NULL')->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @depends testAddAssignment
     */
    public function testMoveCategory()
    {
        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');

        $this->component->rebuildCategoryPath();
        $this->component->rebuildAllAssignments();

        // Move Genusswelten to new parent World of food
        $this->conn->exec('UPDATE s_categories SET parent = 6, path = "|6|3|" WHERE id = 4');

        $result = $this->component->rebuildCategoryPathCount(4);
        $this->assertEquals(1, $result);

        $affectedRows = $this->component->rebuildCategoryPath(4);
        $this->assertEquals(1, $affectedRows, 'Genusswelten child-category Getränke has to be updated');

        $result = $this->component->removeOldAssignmentsCount(4);
        $this->assertEquals(1, $result, 'One Parent-Category has to be cleanen up');

        $affectedRows = $this->component->removeOldAssignments(4);
        $this->assertEquals(2, $affectedRows, 'Two old assignment should be removed');

        $result = $this->component->rebuildAssignmentsCount(4);
        $this->assertEquals(1, $result, 'Affected Categories');

        $affectedRows = $this->component->rebuildAssignments(4);
        $this->assertEquals(3, $affectedRows, '3 new assignments should be created');

        $this->assertEquals(7, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    /**
     * @depends testAddAssignment
     */
    public function testMoveLeafCategory()
    {
        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');

        $this->component->rebuildCategoryPath();
        $this->component->rebuildAllAssignments();

        // Move Getränke to new parent World of food
        $this->conn->exec('UPDATE s_categories SET parent = 6, path = "|6|3|" WHERE id = 5');

        $affectedRows = $this->component->rebuildCategoryPath(5);
        $this->assertEquals(0, $affectedRows, 'Leaf category has no childs that have to be updated');

        $result = $this->component->removeOldAssignmentsCount(5);
        $this->assertEquals(1, $result, 'One category tree is affected');

        $affectedRows = $this->component->removeOldAssignments(5);
        $this->assertEquals(2, $affectedRows, 'Two old assignment should be removed');

        $affectedRows = $this->component->rebuildAssignments(5);
        $this->assertEquals(2, $affectedRows, 'Two new assignments should be created');

        $rows = $this->conn->query('SELECT count(id) FROM s_articles_categories_ro WHERE parentCategoryID = 5')->fetchColumn();
        $this->assertEquals(3, $rows, '3 Rows should be in database');

        $this->assertEquals(6, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testRemoveAllAssignments()
    {
        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');
        $this->component->addAssignment(1, 5);

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');
        $this->component->addAssignment(1, 7);

        $this->assertEquals(6, $this->getConnection()->getRowCount('s_articles_categories_ro'));

        $affectedRows = $this->component->removeAllAssignments();

        $this->assertEquals(6, $affectedRows);
        $this->assertEquals(0, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testRemoveArticleAssignmentments()
    {
        // Assign to Getränke
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 5)');
        $this->component->addAssignment(1, 5);

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');
        $this->component->addAssignment(1, 7);

        // Assign to Spirits
        $this->conn->exec('INSERT INTO s_articles_categories (articleID, categoryID) VALUES (1, 7)');
        $this->component->addAssignment(2, 7);

        $affectedRows = $this->component->removeArticleAssignmentments(1);
        $this->assertEquals(6, $affectedRows);

        $this->assertEquals(3, $this->getConnection()->getRowCount('s_articles_categories_ro'));
    }

    public function testGetParentCategoryIdsForRootLevelReturnsEmptyArray()
    {
        $expectedResult = [];

        $result = $this->component->getParentCategoryIds(1);

        $this->assertEquals($expectedResult, $result);
    }

    public function testRebuildAllAssignmentsCountReturnsZeroIfTableIsEmpty()
    {
        $result = $this->component->rebuildAllAssignmentsCount();

        $this->assertEquals(0, $result);
    }

    public function testLimitWithLimitArgument()
    {
        $statement = 'SELECT * FROM example';

        $expected = 'SELECT * FROM example LIMIT 10';
        $result = $this->component->limit($statement, 10);

        $this->assertEquals($expected, $result);
    }

    public function testLimitWithLimitArgumentAndOffsetNull()
    {
        $statement = 'SELECT * FROM example';

        $expected = 'SELECT * FROM example LIMIT 10';
        $result = $this->component->limit($statement, 10, null);

        $this->assertEquals($expected, $result);
    }

    public function testLimitWithLimitArgumentAndOffset()
    {
        $statement = 'SELECT * FROM example';

        $expected = 'SELECT * FROM example LIMIT 10 OFFSET 20';
        $result = $this->component->limit($statement, 10, 20);

        $this->assertEquals($expected, $result);
    }

    public function testLimitShouldThrowExceptionIfLimitIsLessThanOne()
    {
        $statement = 'SELECT * FROM example';

        $this->expectException(\Exception::class);

        $this->component->limit($statement, 0, 20);
    }

    public function testLimitShouldThrowExceptionIfOffsetIsLessThanOne()
    {
        $statement = 'SELECT * FROM example';

        $this->expectException(\Exception::class);

        $this->component->limit($statement, 5, -1);
    }

    public function testEnableTransactions()
    {
        $this->component->enableTransactions();
        $this->assertTrue($this->component->transactionsEnabled());
    }

    public function testDisableTransactions()
    {
        $this->component->disableTransactions();
        $this->assertFalse($this->component->transactionsEnabled());
    }
}
