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

namespace Shopware\Framework\Config;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Framework;

class ConfigService implements ConfigServiceInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getByShop(string $shopUuid, ?string $parentUuid): array
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->select([
                'e.name',
                'COALESCE(currentShop.value, parentShop.value, fallbackShop.value, e.value) as value',
            ])
            ->from('config_form_field', 'e')
            ->leftJoin('e', 'config_form_field_value', 'currentShop', 'currentShop.config_form_field_uuid = e.uuid AND currentShop.shop_uuid = :currentShopUuid')
            ->leftJoin('e', 'config_form_field_value', 'parentShop', 'parentShop.config_form_field_uuid = e.uuid AND parentShop.shop_uuid = :parentShopUuid')
            ->leftJoin('e', 'config_form_field_value', 'fallbackShop', 'fallbackShop.config_form_field_uuid = e.uuid AND fallbackShop.shop_uuid = :fallbackShopUuid')
            ->leftJoin('e', 'config_form', 'forms', 'forms.uuid = e.config_form_uuid')
            ->setParameter('fallbackShopUuid', 'SWAG-SHOP-UUID-1')
            ->setParameter('currentShopUuid', $shopUuid)
            ->setParameter('parentShopUuid', $parentUuid ?: 'SWAG-SHOP-UUID-1')
        ;

        $data = $builder->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $this->hydrate($data);
    }

    public function hydrate(array $config): array
    {
        $config = array_map('unserialize', $config);
        $config['version'] = Framework::VERSION;
        $config['revision'] = Framework::REVISION;
        $config['versiontext'] = Framework::VERSION_TEXT;

        return $config;
    }
}
