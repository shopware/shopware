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

namespace Shopware\SeoUrl\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\SeoUrl\Struct\SeoUrl;

class SeoUrlWriter
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param SeoUrl[] $urls
     */
    public function create(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                $this->connection->insert(
                    'seo_url',
                    [
                        'name' => $url->getName(),
                        'seo_hash' => $url->getSeoHash(),
                        'foreign_key' => $url->getForeignKey(),
                        'shop_id' => $url->getShopId(),
                        'path_info' => $url->getPathInfo(),
                        'seo_path_info' => $url->getSeoPathInfo(),
                        'is_canonical' => $url->isCanonical(),
                        'created_at' => $url->getCreatedAt()->format('Y-m-d H:i:s'),
                    ]
                );
            } catch (\Exception $e) {
            }
        }
    }

    public function delete(array $ids): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM seo_url WHERE id IN (:ids)',
            [':ids' => $ids],
            [':ids' => Connection::PARAM_INT_ARRAY]
        );
    }
}
