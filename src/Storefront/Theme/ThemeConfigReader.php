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

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;

class ThemeConfigReader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function get(): array
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->select([
            'LOWER(REPLACE(e.name, "_", "")) as name',
            'COALESCE(currentTheme.value, e.default_value) as value',
        ])
            ->from('shop_template_config_form_field', 'e')
            ->leftJoin('e', 'shop_template_config_form_field_value', 'currentTheme', 'currentTheme.shop_template_config_form_field_uuid = e.uuid')
//            ->leftJoin('e', 's_core_templates_config_values', 'parentTheme', 'parentShop.element_id = e.id AND parentTheme.template_id = :parentThemeId')
            ->setParameter('currentThemeId', 23)
//            ->setParameter('parentThemeId', 0)
            //->setParameter('currentThemeId', $theme->getId())
            //->setParameter('parentShopId', $theme->getParentId())
        ;

        $data = $builder->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $this->hydrate($data);
    }

    public function hydrate(array $config): array
    {
        $config = array_map('unserialize', $config);

        return $config;
    }
}
