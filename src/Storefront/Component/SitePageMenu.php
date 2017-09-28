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

namespace Shopware\Storefront\Component;

use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\RouterInterface;

class SitePageMenu
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param Connection      $connection
     * @param RouterInterface $router
     */
    public function __construct(Connection $connection, RouterInterface $router)
    {
        $this->connection = $connection;
        $this->router = $router;
    }

    /**
     * Returns a shop page tree for the provided shop id.
     *
     * @param $shopId
     * @param $activeId
     *
     * @return array
     */
    public function getTree(int $shopId, ?int $activeId = null): array
    {
        $query = $this->getQuery($shopId);

        /** @var $statement \PDOStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $menu = [];
        $links = [];
        foreach ($data as $site) {
            $key = !empty($site['mapping']) ? $site['mapping'] : $site['group'];

            if ($this->overrideExisting($menu, $key, $site)) {
                $menu[$key] = [];
            }

            $id = (int) $site['id'];
            if (!empty($site['link'])) {
                $links[$id] = $site['link'];
            } else {
                $links[$id] = [
                    'controller' => 'custom',
                    'action' => 'index',
                    'sCustom' => $id,
                ];
            }

            $menu[$key][] = $site;
        }

        /** @var Router $router */
        //        $seoUrls = $this->router->generateList($links);
        $seoUrls = [];
        $menu = $this->assignSeoUrls($menu, $seoUrls);

        $result = [];
        foreach ($menu as $key => $group) {
            $sites = $this->buildSiteTree(0, $group, $activeId);
            $result[$key] = $sites;
        }

        return $result;
    }

    /**
     * Checks if the provided menu contains already an entry for the provided site.
     * If the provided site contains a mapping but the existing not, override the existing.
     *
     * @param $menu
     * @param $key
     * @param $site
     *
     * @return bool
     */
    public function overrideExisting($menu, $key, $site): bool
    {
        return !empty($site['mapping']) && empty($menu[$key][0]['mapping']);
    }

    /**
     * @param $parentId
     * @param $sites
     * @param $activeId
     *
     * @return array
     */
    private function buildSiteTree($parentId, $sites, $activeId): array
    {
        $result = [];
        foreach ($sites as $index => $site) {
            $site['active'] = ($site['id'] == $activeId);

            if ($site['parentID'] != $parentId) {
                continue;
            }
            $id = (int) $site['id'];

            //call recursive for tree building
            $site['subPages'] = $this->buildSiteTree(
                $site['id'],
                $sites,
                $activeId
            );

            if (!$site['active'] && count($site['subPages']) > 0) {
                $site['active'] = max(array_column($site['subPages'], 'active'));
            }

            $site['childrenCount'] = count($site['subPages']);

            $result[$id] = $site;
        }

        return array_values($result);
    }

    /**
     * @param $shopId
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getQuery($shopId): \Doctrine\DBAL\Query\QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();

        $query->select([
            'page.id',
            'page.description',
            'page.link',
            'page.target',
            'page.parentID',
            'groups.key as `group`',
            'mapping.key as mapping',
        ]);

        $query->from('s_cms_static', 'page');

        $query->leftJoin(
            'page',
            's_cms_static_groups',
            'groups',
            'groups.active = 1'
        );

        $query->leftJoin(
            'groups',
            's_cms_static_groups',
            'mapping',
            'groups.mapping_id = mapping.id'
        );

        $query->leftJoin(
            'groups',
            's_core_shop_pages',
            'shops',
            'groups.id = shops.group_id AND shops.shop_id = :shopId'
        );

        $query->andWhere('groups.active = 1')
            ->andWhere("CONCAT('|', page.grouping, '|') LIKE CONCAT('%|', groups.key, '|%')")
            ->andWhere('(mapping.id IS NULL OR shops.shop_id IS NOT NULL)')
            ->andWhere('(mapping.id IS NULL OR mapping.active=1)')
            ->andWhere('(page.shop_ids IS NULL OR page.shop_ids LIKE :staticShopId)');

        $query
            ->orderBy('parentID', 'ASC')
            ->addOrderBy('mapping.key')
            ->addOrderBy('page.position')
            ->addOrderBy('page.description');

        $query->setParameter('shopId', $shopId)
            ->setParameter('staticShopId', '%|' . $shopId . '|%');

        return $query;
    }

    /**
     * @param array[]  $menu
     * @param string[] $seoUrls
     *
     * @return array
     */
    private function assignSeoUrls($menu, $seoUrls): array
    {
        foreach ($menu as &$group) {
            foreach ($group as &$site) {
                $key = (int) $site['id'];
                if (array_key_exists($key, $seoUrls)) {
                    $site['link'] = $seoUrls[$key];
                }
            }
        }

        return $menu;
    }
}
