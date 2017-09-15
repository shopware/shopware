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

namespace Shopware\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Shop\ShopRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

class ShopFinder
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function findShopByRequest(RequestContext $requestContext, Request $request): ?array
    {
        $shop = $this->getShopByPost($requestContext, $request);
        if ($shop !== null) {
            return $shop;
        }

        $shop = $this->getShopByCookie($request);
        if ($shop !== null) {
            return $shop;
        }

        $query = $this->createQuery();
        $query->andWhere('shop.active = 1');

        //first use default shop than main shops
        $query->addOrderBy('shop.is_default', 'DESC');
        $query->addOrderBy('shop.main_id', 'ASC');

        $shops = $query->execute()->fetchAll();

        $paths = [];

        foreach ($shops as &$shop) {
            $base = $shop['base_url'] ?? $shop['base_path'];

            $shop = $this->fixUrls($shop);

            $base = rtrim($base, '/') . '/';

            if (array_key_exists($base, $paths)) {
                continue;
            }

            $paths[$base] = $shop;
        }

        $url = rtrim($requestContext->getBaseUrl(), '/') . '/';

        // direct hit
        if (array_key_exists($url, $paths)) {
            return $paths[$url];
        }

        // reduce shops to which base url is the beginning of the request
        $paths = array_filter($paths, function ($baseUrl) use ($url) {
            return strpos($url, $baseUrl) === 0;
        }, ARRAY_FILTER_USE_KEY);

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = current($shops);
        foreach ($paths as $baseUrl => $shop) {
            if (strlen($baseUrl) > strlen($lastBaseUrl)) {
                $bestMatch = $shop;
            }

            $lastBaseUrl = $baseUrl;
        }

        return $bestMatch;
    }

    private function getShopByCookie(Request $request): ?array
    {
        //use shop cookie before detect shop by url
        if (!$request->cookies->has('shop')) {
            return null;
        }

        $query = $this->createQuery();
        $query->andWhere('shop.uuid = :uuid');
        $query->setParameter('uuid', $request->cookies->get('shop'));
        $shop = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($shop) {
            return $shop;
        }
        $request->cookies->set('shop', null);

        return null;
    }

    protected function createQuery(): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'shop.uuid',
            'shop.parent_uuid',
            'shop.base_url',
            'shop.hosts',
            'shop.category_uuid',
            'shop.locale_uuid',
            'shop.currency_uuid',
            'shop.customer_group_uuid',
            'shop.fallback_locale_uuid',
            'shop.customer_scope',
            'shop.is_default',
            'shop.active',
            'shop.host',
            'shop.base_path',
            'shop.is_secure',
            'locale.code as locale_code'
        ]);
        $query->from('shop', 'shop');
        $query->innerJoin('shop', 'locale', 'locale', 'locale.uuid = shop.locale_uuid');

        return $query;
    }

    private function fixUrls(array $shop): array
    {
        $shop['base_url'] = rtrim($shop['base_url'], '/') . '/';
        $shop['base_path'] = rtrim($shop['base_path'], '/') . '/';

        return $shop;
    }

    private function getShopByPost(RequestContext $context, Request $request): ?array
    {
        if ($context->getMethod() !== 'POST') {
            return null;
        }

        if (!$request->get('__shop')) {
            return null;
        }

        $query = $this->createQuery();
        $query->andWhere('shop.uuid = :uuid');
        $query->setParameter('uuid',  $request->get('__shop'));
        $shop = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($shop) {
            return $shop;
        }

        return null;
    }
}
