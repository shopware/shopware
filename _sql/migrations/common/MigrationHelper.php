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

class MigrationHelper
{
    /**
     * @var \PDO
     */
    private $connection;

    /**
     *
     * @param \PDO $connection
     */
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $table
     * @return array[]
     */
    public function getList($table)
    {
        $identifiers = ['id', 'billingID', 'shippingID'];

        $columns = $this->connection->query('DESCRIBE ' . $table)->fetchAll(\PDO::FETCH_ASSOC);

        $definition = [];

        foreach ($columns as $column) {
            if (in_array($column['Field'], $identifiers)) {
                continue;
            }
            $definition[] = ['name' => $column['Field'], 'type' => $column['Type']];
        }

        return $definition;
    }

    /**
     * @param string $table
     * @param string $name
     * @param string $type
     */
    public function update($table, $name, $type)
    {
        if ($this->get($table, $name) !== null) {
            $this->changeColumn($table, $name, $type);
            return;
        }

        $this->createColumn($table, $name, $type);
    }

    /**
     * @param string $table
     * @param string $name
     * @param string $type
     */
    private function createColumn($table, $name, $type)
    {
        $sql = sprintf("ALTER TABLE `%s` ADD `%s` %s NULL DEFAULT NULL", $table, $name, $type);
        $this->connection->exec($sql);
    }

    /**
     * @param string $table
     * @param string $name
     * @param string $type
     */
    private function changeColumn($table, $name, $type)
    {
        $sql = sprintf("ALTER TABLE `%s` CHANGE `%s` `%s` %s NULL DEFAULT NULL;", $table, $name, $name, $type);
        $this->connection->exec($sql);
    }

    /**
     * @param string $table
     * @param string $name
     * @return null|array
     */
    private function get($table, $name)
    {
        $columns = $this->getList($table);
        foreach ($columns as $column) {
            if ($name == $column['name']) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param string $table
     * @param string $keyColumn
     */
    public function migrateAttributes($table, $keyColumn)
    {
        $attributes = $this->getList($table);

        if (empty($attributes)) {
            return;
        }

        $names = array_column($attributes, 'name');

        $prefixed = array_map(function ($name) {
            return 'attr.' . $name;
        }, $names);

        $names = implode(',', $names);
        $prefixed = implode(',', $prefixed);

        $type = str_replace('_attributes', '', $table);

        $sql = <<<SQL
          INSERT IGNORE INTO s_user_addresses_attributes (address_id, $names)
          SELECT
              address.id as address_id,
              $prefixed
          FROM s_user_addresses address
            INNER JOIN $table as attr
              ON address.original_id = attr.$keyColumn
              AND address.original_type = '$type'
SQL;

        $this->connection->exec($sql);
    }
}
