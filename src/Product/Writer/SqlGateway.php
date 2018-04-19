<?php declare(strict_types=1);
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

namespace Shopware\Product\Writer;

use Doctrine\DBAL\Connection;

class SqlGateway
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->tableName = 'product';
    }

    public function insert(array $data): void
    {
        $this->connection->transactional(function () use ($data) {
            $affectedRows = $this->connection->insert(
                $this->tableName,
                $data
            );

            if (!$affectedRows) {
                throw new ExceptionNoInsertedRecord('Unable to insert data');
            }
        });
    }

    public function update(string $uuid, array $data): void
    {
        $this->connection->transactional(function () use ($uuid, $data) {
            $affectedRows = $this->connection->update(
                $this->tableName,
                $data,
                ['uuid' => $uuid]
            );

            if (0 === $affectedRows) {
                throw new ExceptionNoUpdatedRecord(sprintf('Unable to update "%s" - no rows updated', $uuid));
            }

            if (1 > $affectedRows) {
                throw new ExceptionMultipleUpdatedRecord(sprintf('Unable to update "%s" - multiple rows updated', $uuid));
            }
        });
    }
}
