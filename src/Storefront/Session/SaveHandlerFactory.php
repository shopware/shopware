<?php
declare(strict_types=1);
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

namespace Shopware\Storefront\Session;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SaveHandlerFactory
{
    /**
     * @var \PDO
     */
    private $connection;

    /**
     * @var string
     */
    private $table;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function createSaveHandler(array $sessionOptions): ?\SessionHandlerInterface
    {
        if (empty($sessionOptions['save_handler']) || $sessionOptions['save_handler'] !== 'db') {
            $this->setPhpIniSettings($sessionOptions);

            return null;
        }

        return new PdoSessionHandler(
            $this->connection->getWrappedConnection(),
            [
                'db_table' => $this->table,
                'db_id_col' => 'id',
                'db_data_col' => 'data',
                'db_expiry_col' => 'expiry',
                'db_time_col' => 'modified',
                'db_lifetime_col' => 'lifetime',
                'lock_mode' => $sessionOptions['locking'] ? PdoSessionHandler::LOCK_TRANSACTIONAL : PdoSessionHandler::LOCK_NONE,
            ]
        );
    }

    private function setPhpIniSettings(array $sessionOptions): void
    {
        $sessionOptions = array_filter($sessionOptions);

        foreach ($sessionOptions as $key => $value) {
            ini_set('session.' . $key, $value);
        }
    }
}
