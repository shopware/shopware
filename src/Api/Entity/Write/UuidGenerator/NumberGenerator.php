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

namespace Shopware\Api\Entity\Write\UuidGenerator;

use Doctrine\DBAL\Connection;

abstract class NumberGenerator implements Generator
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $prefix;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection, string $name, string $prefix)
    {
        $this->connection = $connection;
        $this->name = $name;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function create(): string
    {
        $this->connection->beginTransaction();
        try {
            $number = $this->connection->fetchColumn('SELECT number FROM s_order_number WHERE name = ? FOR UPDATE', [$this->name]);

            if ($number === false) {
                throw new \RuntimeException(sprintf('Number range with name "%s" does not exist.', $this->name));
            }

            $number += 1000;

            $this->connection->executeUpdate('UPDATE s_order_number SET number = number + 1 WHERE name = ?', [$this->name]);
            ++$number;
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $this->prefix . $number;
    }
}
