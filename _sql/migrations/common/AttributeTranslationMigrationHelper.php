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

/**
 * This helper class extracts the logic for the migration of translated article attributes from Shopware 5.1 to 5.2.
 * This allows to split this migration into multiple (ten in this case) steps and therefor makes it possible to migrate
 * a greater amount of translatable article attributes on low performance hosting solutions.
 */
class AttributeTranslationMigrationHelper
{
    /**
     * @var PDO
     */
    private $connection;

    /**
     * @param PDO $connection
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int $maxCount
     * @return int
     */
    public function migrate($maxCount)
    {
        $maxId = $this->connection->query('SELECT max_id FROM translation_migration_id')->fetch(PDO::FETCH_COLUMN);

        $columns = $this->getColumns();

        $statement = $this->connection->prepare(
<<<EOL
          SELECT * 
          FROM s_core_translations
          WHERE objecttype IN ("article", "variantMain", "variant")
          AND id > :lastId
          AND id <= :maxId
          ORDER BY id ASC
          LIMIT 250
EOL
        );

        $lastId = 0;
        $statement->execute([':lastId' => $lastId, ':maxId' => $maxId]);
        $count = 0;

        while ($rows = $statement->fetchAll(PDO::FETCH_ASSOC)) {
            if (empty($rows)) {
                break;
            }

            $values = $this->getUpdatedTranslations($rows, $columns);
            if (empty($values)) {
                continue;
            }

            $ids = array_keys($values);

            $this->connection->exec('DELETE FROM s_core_translations WHERE id IN (' . implode(',', $ids) . ')');
            $this->connection->exec(
                'INSERT INTO s_core_translations (objecttype, objectdata, objectkey, objectlanguage) VALUES ' . implode(',', $values)
            );

            $lastId = array_pop($rows)['id'];
            $statement->execute([':lastId' => $lastId, ':maxId' => $maxId]);

            $count += count($rows);
            if ($count > $maxCount) {
                break;
            }
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function getColumns()
    {
        $columns = $this->connection->query('SHOW COLUMNS FROM s_articles_attributes')->fetchAll(PDO::FETCH_ASSOC);

        $columns = array_column($columns, 'Field');

        $mapping = [];
        foreach ($columns as $column) {
            $mapping[$column] = $column;
            $camelCase = $this->underscoreToCamelCase($column);
            $mapping[$camelCase] = $column;
        }

        return $mapping;
    }

    /**
     * @param string $str
     * @return string
     */
    private function underscoreToCamelCase($str)
    {
        return preg_replace_callback('/_([a-zA-Z])/', function ($c) {
            return strtoupper($c[1]);
        }, $str);
    }

    /**
     * @param array $data
     * @param array $columns
     * @return null|array
     */
    private function filter($data, array $columns)
    {
        if (!is_array($data)) {
            return null;
        }

        $updated = false;
        foreach ($columns as $key => $column) {
            if (array_key_exists($key, $data)) {
                $newKey = '__attribute_' . $column;

                if (!array_key_exists($newKey, $data)) {
                    $data[$newKey] = $data[$key];
                    $updated = true;
                }
            }
        }

        if (!$updated) {
            return null;
        }

        return $data;
    }

    /**
     * @param array[] $rows
     * @param string[] $columns
     * @return string[] indexed by translation id
     */
    private function getUpdatedTranslations($rows, $columns)
    {
        $values = [];

        foreach ($rows as $row) {
            try {
                $updated = $this->filter(unserialize($row['objectdata']), $columns);
            } catch (Exception $e) {
                //serialize error - continue with next translation
                continue;
            }

            if ($updated === null) {
                continue;
            }

            $row = array_map(function ($value) {
                return $this->connection->quote($value);
            }, $row);

            $updated = $this->connection->quote(serialize($updated));

            $values[$row['id']] = "({$row['objecttype']}, {$updated}, {$row['objectkey']}, {$row['objectlanguage']})";
        }

        return $values;
    }
}
