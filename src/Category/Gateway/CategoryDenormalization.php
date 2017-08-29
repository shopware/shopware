<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Category\Gateway;

use Doctrine\DBAL\Connection;

/**
 * CategoryDenormalization-Class
 * This class contains various methods to maintain
 * the denormalized representation of the Product to Category assignments.
 * The assignments between products and categories are stored in product_category.
 * The table product_category_ro contains each assignment of product_category
 * plus additional assignments for each child category.
 * Most write operations take place in product_category_ro.
 *
 * @category  Shopware
 * @package   Shopware\Components\Model
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryDenormalization
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns an array of all categoryIds the given $id has as parent
     * Example:
     * $id = 9
     * <code>
     * Array
     * (
     *     [0] => 9
     *     [1] => 5
     *     [2] => 10
     *     [3] => 3
     * )
     * <code>
     *
     * @param string $uuid
     * @return array
     */
    public function getParentCategoryUuids(string $uuid): array
    {
        $stmt = $this->connection
            ->prepare('SELECT uuid, parent_uuid FROM category WHERE uuid = :uuid AND parent_uuid IS NOT NULL');
        $stmt->execute([':uuid' => $uuid]);
        $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$parent) {
            return [];
        }

        $result = [$parent['uuid']];

        $parent = $this->getParentCategoryUuids($parent['parent_uuid']);
        if ($parent) {
            $result = array_merge($result, $parent);
        }

        return $result;
    }

    /**
     * Returns count for paging rebuildCategoryPath()
     *
     * @param string $categoryUuid
     * @return int
     */
    public function rebuildCategoryPathCount($categoryUuid = null): int
    {
        if ($categoryUuid === null) {
            $sql = '
                SELECT count(uuid)
                FROM category
                WHERE parent_uuid IS NOT NULL
            ';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
        } else {
            $sql = '
                SELECT count(c.uuid)
                FROM  category c
                WHERE c.path LIKE :categoryPath
            ';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['categoryPath' => '%|' . $categoryUuid . '|%']);
        }

        $count = $stmt->fetchColumn();

        return (int) $count;
    }

    /**
     * Sets path for child categories of given $categoryId
     *
     * @param string $categoryUuid
     * @param int $count
     * @param int $offset
     * @return int
     */
    public function rebuildCategoryPath($categoryUuid = null, $count = null, $offset = 0): int
    {
        $parameters = [];
        if ($categoryUuid === null) {
            $sql = '
                SELECT uuid, path
                FROM  category
                WHERE parent_uuid IS NOT NULL
            ';
        } else {
            $sql = '
                SELECT id, path
                FROM  category
                WHERE path LIKE :categoryPath
            ';

            $parameters = [
                'categoryPath' => '%|' . $categoryUuid . '|%',
            ];
        }

        if ($count !== null) {
            $sql = $this->limit($sql, $count, $offset);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($parameters);

        $count = 0;

        while ($category = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $count += $this->rebuildPath($category['uuid'], $category['path']);
        }

        return $count;
    }

    /**
     * Rebuilds the path for a single category
     *
     * @param string $categoryUuid
     * @param $categoryPath
     * @return int
     */
    public function rebuildPath(string $categoryUuid, $categoryPath = null): int
    {
        $updateStmt = $this->connection->prepare('UPDATE category SET path = :path WHERE uuid = :categoryUuid');

        $parents = $this->getParentCategoryUuids($categoryUuid);
        array_shift($parents);

        if (empty($parents)) {
            $path = null;
        } else {
            $path = '|' . implode('|', $parents) . '|';
        }

        if ($categoryPath !== $path) {
            $updateStmt->execute([':path' => $path, ':categoryUuid' => $categoryUuid]);

            return 1;
        }

        return 0;
    }

    /**
     * Rebuilds the path for a single category
     *
     * @param string $categoryUuid
     * @return int
     */
    public function removeOldAssignmentsCount(string $categoryUuid): int
    {
        $sql = '
            SELECT parent_category_uuid
            FROM product_category_ro
            WHERE category_uuid = :categoryUuid
            AND parent_category_uuid <> category_uuid
            GROUP BY parent_category_uuid
        ';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['categoryUuid' => $categoryUuid]);

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // in case that a leaf category is moved
        if (empty($rows)) {
            return 1;
        }

        return count($rows);
    }

    /**
     * Used for category movement.
     * If Category is moved to a new parentId this returns removes old connections
     *
     * @param string $categoryUUid
     * @param int $count
     * @param int $offset
     * @return int
     */
    public function removeOldAssignments(string $categoryUUid, $count = null, $offset = 0): int
    {
        $sql = '
            SELECT parent_category_uuid
            FROM product_category_ro
            WHERE category_uuid = :categoryUuid
            AND parent_category_uuid <> category_uuid
            GROUP BY parent_category_uuid
       ';

        if ($count !== null) {
            $sql = $this->limit($sql, $count, $offset);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['categoryUuid' => $categoryUUid]);

        $deleteStmt = $this->connection->prepare(
            'DELETE FROM product_category_ro
             WHERE parent_category_uuid = :categoryUuid
             AND parent_category_uuid <> product_category_ro.category_uuid'
        );

        $count = 0;

        $parentCategoryUuid = $stmt->fetchColumn();

        if ($parentCategoryUuid) {
            do {
                $deleteStmt->execute(['categoryUuid' => $parentCategoryUuid]);
                $count += $deleteStmt->rowCount();
            } while ($parentCategoryUuid = $stmt->fetchColumn());
        } else {
            $deleteStmt->execute(['categoryUuid' => $categoryUUid]);
            $count += $deleteStmt->rowCount();
        }

        return $count;
    }

    /**
     * Returns count for paging rebuildAssignmentsCount()
     *
     * @param string $categoryUuid
     * @return int
     */
    public function rebuildAssignmentsCount(string $categoryUuid): int
    {
        $sql = '
            SELECT c.uuid
            FROM  category c
            INNER JOIN product_category ac ON ac.category_uuid = c.uuid
            WHERE c.path LIKE :categoryPath
            GROUP BY c.uuid
        ';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['categoryPath' => '%|' . $categoryUuid . '|%']);

        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // in case that a leaf category is moved
        if (empty($rows)) {
            return 1;
        }

        return count($rows);
    }

    /**
     * @param string $categoryUuid
     * @param int $count
     * @param int $offset
     * @return int
     */
    public function rebuildAssignments(string $categoryUuid, $count = null, $offset = 0): int
    {
        $affectedCategoriesSql = '
            SELECT c.uuid
            FROM  category c
            INNER JOIN product_category ac ON ac.category_uuid = c.uuid
            WHERE c.path LIKE :categoryUuid
            GROUP BY c.uuid
        ';

        if ($count !== null) {
            $affectedCategoriesSql = $this->limit($affectedCategoriesSql, $count, $offset);
        }

        $stmt = $this->connection->prepare($affectedCategoriesSql);
        $stmt->execute(['categoryUuid' => '%|' . $categoryUuid . '|%']);

        $affectedCategories = [];
        while ($row = $stmt->fetchColumn()) {
            $affectedCategories[] = $row;
        }

        // in case that a leaf category is moved
        if (count($affectedCategories) === 0) {
            $affectedCategories = [$categoryUuid];
        }

        $assignmentsSql = 'SELECT product_uuid, category_uuid
                           FROM `product_category`
                           WHERE category_uuid = :categoryUuid';
        $assignmentsStmt = $this->connection->prepare($assignmentsSql);

        $count = 0;

        foreach ($affectedCategories as $uuid) {
            $assignmentsStmt->execute(['categoryUuid' => $uuid]);

            while ($assignment = $assignmentsStmt->fetch()) {
                $count += $this->insertAssignment($assignment['product_uuid'], $assignment['category_uuid']);
            }
        }

        return $count;
    }

    /**
     * Returns maxcount for paging rebuildAllAssignmentsCount()
     *
     * @return int
     */
    public function rebuildAllAssignmentsCount(): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM  product_category ac
            INNER JOIN category c
            ON ac.category_uuid = c.uuid
        ';

        $stmt = $this->connection->query($sql);
        $rows = $stmt->fetchColumn();

        return (int) $rows;
    }

    /**
     * @param int $count maximum number of assignments to denormalize
     * @param int $offset
     * @return int number of new denormalized assignments
     */
    public function rebuildAllAssignments($count = null, $offset = 0): int
    {
        $allAssignsSql = '
            SELECT ac.product_uuid, ac.category_uuid, c.parent_uuid
            FROM product_category ac
            INNER JOIN category c ON ac.category_uuid = c.uuid
            LEFT JOIN category c2 ON c.uuid = c2.parent_uuid
            WHERE c2.uuid IS NULL
            GROUP BY ac.product_uuid, ac.category_uuid, c.parent_uuid
            ORDER BY product_uuid, category_uuid
        ';

        if ($count !== null) {
            $allAssignsSql = $this->limit($allAssignsSql, $count, $offset);
        }

        $assignments = $this->connection->query($allAssignsSql);

        $newRows = 0;
        while ($assignment = $assignments->fetch()) {
            $newRows += $this->insertAssignment($assignment['product_uuid'], $assignment['category_uuid']);
        }

        return $newRows;
    }

    public function buildProductAssignments(string $productUuid): int
    {
        $deleted = $this->removeProductAssignments($productUuid);

        $allAssignsSql = '
            SELECT ac.product_uuid, ac.category_uuid, c.parent_uuid
            FROM product_category ac
            INNER JOIN category c ON ac.category_uuid = c.uuid
            LEFT JOIN category c2 ON c.uuid = c2.parent_uuid
            WHERE c2.uuid IS NULL 
            AND ac.product_uuid = :productUuid
            GROUP BY ac.product_uuid, ac.category_uuid, c.parent_uuid
            ORDER BY product_uuid, category_uuid
        ';

        $assignments = $this->connection
            ->executeQuery(
                $allAssignsSql,
                [':productUuid' => $productUuid]
            );

        $newRows = 0;
        while ($assignment = $assignments->fetch()) {
            $newRows += $this->insertAssignment($assignment['product_uuid'], $assignment['category_uuid']);
        }

        return $newRows;
    }

    /**
     * Removes assignments in product_category_ro
     *
     * @param string $productUuid
     * @param string $categoryUuid
     * @return int
     */
    public function removeAssignment(string $productUuid, string $categoryUuid): int
    {
        $deleteQuery = '
            DELETE FROM product_category_ro
            WHERE parent_category_uuid = :category_uuid
            AND product_uuid = :product_uuid
        ';

        $stmt = $this->connection->prepare($deleteQuery);
        $stmt->execute(
            [
                'category_uuid' => $categoryUuid,
                'product_uuid' => $productUuid,
            ]
        );

        return $stmt->rowCount();
    }

    /**
     * Adds new assignment between $articleId and $categoryId
     *
     * @param string $productUuid
     * @param string $categoryUuid
     */
    public function addAssignment(string $productUuid, string $categoryUuid): void
    {
        $this->insertAssignment($productUuid, $categoryUuid);
    }

    /**
     * Removes all connections for given $articleId
     *
     * @param string $productUuid
     * @return int count of deleted rows
     */
    public function removeProductAssignments(string $productUuid): int
    {
        $deleteQuery = '
            DELETE
            FROM product_category_ro
            WHERE product_uuid = :productUuid
        ';

        $stmt = $this->connection->prepare($deleteQuery);
        $stmt->execute([':productUuid' => $productUuid]);

        return $stmt->rowCount();
    }

    /**
     * Removes all connections for given $categoryId
     *
     * @param string $categoryUuid
     * @return int count of deleted rows
     */
    public function removeCategoryAssignmentments(string $categoryUuid): int
    {
        $deleteQuery = '
            DELETE ac1
            FROM product_category_ro ac0
            INNER JOIN product_category_ro ac1
                ON ac0.parent_category_uuid = ac1.parent_category_uuid
                -- AND ac0.id != ac1.
            WHERE ac0.category_uuid = :categoryUuid
        ';

        $stmt = $this->connection->prepare($deleteQuery);
        $stmt->execute(['categoryUuid' => $categoryUuid]);

        return $stmt->rowCount();
    }

    /**
     * First try to truncate table,
     * if that Fails due to insufficient permissions, use delete query
     *
     * @return int
     */
    public function removeAllAssignments(): int
    {
        // TRUNCATE is faster than DELETE
        try {
            $count = $this->connection->exec('TRUNCATE product_category_ro');
        } catch (\PDOException $e) {
            $count = $this->connection->exec('DELETE FROM product_category_ro');
        }

        return $count;
    }

    /**
     * Removes assignments for non-existing articles or categories
     *
     * @return int
     */
    public function removeOrphanedAssignments(): int
    {
        $deleteOrphanedSql = '
            DELETE ac.*
            FROM product_category ac
            LEFT JOIN category c ON ac.category_uuid = c.uuid
            LEFT JOIN product a ON ac.product_uuid = a.uuid
            WHERE c.uuid IS NULL
            OR a.uuid IS NULL
        ';

        return $this->connection->exec($deleteOrphanedSql);
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql
     * @param int $count
     * @param int $offset OPTIONAL
     * @throws \Exception
     * @return string
     */
    public function limit(string $sql, int $count, int $offset = 0): string
    {
        $sql .= " LIMIT $count";
        if ($offset > 0) {
            $sql .= " OFFSET $offset";
        }

        return $sql;
    }

    private function insertAssignment(string $productUuid, string $categoryUuid): int
    {
        $count = 0;

        $parents = $this->getParentCategoryUuids($categoryUuid);
        if (empty($parents)) {
            return $count;
        }

        $selectSql = '
            SELECT *
            FROM product_category_ro
            WHERE category_uuid        = :categoryUuid
            AND   product_uuid         = :productUuid
            AND   parent_category_uuid = :parentCategoryUuid
        ';

        $selectStmt = $this->connection->prepare($selectSql);

        $insertSql = 'INSERT INTO product_category_ro (product_uuid, category_uuid, parent_category_uuid)
                      VALUES (:productUuid, :categoryUuid, :parentCategoryUuid)';
        $insertStmt = $this->connection->prepare($insertSql);

        foreach ($parents as $parentUuid) {
            $selectStmt->execute(
                [
                    ':productUuid' => $productUuid,
                    ':categoryUuid' => $parentUuid,
                    ':parentCategoryUuid' => $categoryUuid,
                ]
            );

            if ($selectStmt->fetchColumn() === false) {
                ++$count;

                $insertStmt->execute(
                    [
                        ':productUuid' => $productUuid,
                        ':categoryUuid' => $parentUuid,
                        ':parentCategoryUuid' => $categoryUuid,
                    ]
                );
            }
        }

        return $count;
    }
}
