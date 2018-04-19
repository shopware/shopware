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
use Shopware\Context\Struct\ShopContext;
use Shopware\Storefront\Session\ShopSubscriber;
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

    public function findShopByRequest(RequestContext $requestContext, Request $request): array
    {
        //shop switcher used?
        if ($requestContext->getMethod() === 'POST' && $request->get('__shop')) {
            $shop = $this->loadShop((int) $request->get('__shop'));
            return $shop;
        }

        //use shop cookie before detect shop by url
        if ($request->cookies->get('shop')) {
            return $this->loadShop((int) $request->cookies->get('shop'));
        }

        if ($request->attributes->has('_shop')) {
            return $request->attributes->get('_shop');
        }

        if ($request->attributes->has(ShopSubscriber::SHOP_CONTEXT_PROPERTY)) {
            /** @var ShopContext $context */
            $context = $request->attributes->get(ShopSubscriber::SHOP_CONTEXT_PROPERTY);
            return $this->loadShop($context->getShop()->getId());
        }

        $query = $this->createQuery();
        $query->andWhere('shop.active = 1');
        //first use default shop than main shops
        $query->addOrderBy('shop.default', 'DESC');
        $query->addOrderBy('shop.main_id', 'ASC');

        $shops = $query->execute()->fetchAll();

        $paths = [];

        foreach ($shops as &$shop) {
            $base = $shop['base_url'] ?? $shop['base_path'];

            $shop = $this->fixUrls($shop);

            $base = rtrim($base, '/') . '/';
            $paths[$base] = $shop;
        }

        $url = rtrim($requestContext->getPathInfo(), '/') . '/';

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

    private function loadShop(int $shopId): array
    {
        $query = $this->createQuery();
        $query->andWhere('shop.id = :shopId');
        $query->setParameter('shopId', $shopId);
        $shop = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return $this->fixUrls($shop);
    }

    /**
     * @return QueryBuilder
     */
    protected function createQuery(): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'shop.id',
            'shop.uuid',
            'shop.main_id',
            'shop.name',
            'shop.title',
            'shop.position',
            'shop.base_url',
            'shop.hosts',
            'shop.category_id',
            'shop.locale_id',
            'shop.currency_id',
            'shop.customer_group_id',
            'shop.fallback_id',
            'shop.customer_scope',
            'shop.default',
            'shop.active',
            "COALESCE(shop.host, main.host, 'localhost') as host",
            "COALESCE(shop.base_path, main.base_path, '') as base_path",
            "COALESCE(shop.secure, main.secure) as secure",
            "COALESCE(shop.template_id, main.template_id) as template_id",
            "COALESCE(shop.document_template_id, main.document_template_id) as document_template_id",
            "COALESCE(shop.payment_id, main.payment_id) as payment_id",
            "COALESCE(shop.dispatch_id, main.dispatch_id) as dispatch_id",
            "COALESCE(shop.country_id, main.country_id) as country_id",
            "COALESCE(shop.tax_calculation_type, main.tax_calculation_type) as tax_calculation_type",
            'locale.locale'
        ]);
        $query->from('s_core_shops', 'shop');
        $query->leftJoin('shop', 's_core_shops', 'main', 'main.id = shop.main_id');
        $query->innerJoin('shop', 's_core_locales', 'locale', 'locale.id=shop.locale_id');
        
        return $query;
    }

    /**
     * @param $shop
     * @return mixed
     */
    protected function fixUrls($shop)
    {
        $shop['base_url'] = rtrim($shop['base_url'], '/').'/';
        $shop['base_path'] = rtrim($shop['base_path'], '/').'/';

        return $shop;
    }
}
