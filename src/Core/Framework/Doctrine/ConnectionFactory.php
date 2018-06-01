<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Shopware\Core\Kernel;

class ConnectionFactory extends \Doctrine\Bundle\DoctrineBundle\ConnectionFactory
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(array $typesConfig, Kernel $kernel)
    {
        parent::__construct($typesConfig);

        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function createConnection(
        array $params,
        Configuration $config = null,
        EventManager $eventManager = null,
        array $mappingTypes = []
    ): Connection {
        $params['pdo'] = $this->kernel::getConnection();

        // remove url from parameters as doctrine would create a new connection
        // and does not use the existing pdo connection.
        unset($params['url']);

        return parent::createConnection(
            $params,
            $config,
            $eventManager,
            $mappingTypes
        );
    }
}
