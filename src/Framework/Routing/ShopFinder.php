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
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\ShopRepository;
use Shopware\Shop\Struct\ShopBasicStruct;
use Shopware\Storefront\Session\ShopSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

class ShopFinder
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ShopRepository $shopRepository, Connection $connection)
    {
        $this->shopRepository = $shopRepository;
        $this->connection = $connection;
    }

    public function findShopByRequest(RequestContext $requestContext, Request $request): ShopBasicStruct
    {
        //shop switcher used?
        if ($requestContext->getMethod() === 'POST' && $request->get('__shop')) {
            $shop = $this->loadShop((string) $request->get('__shop'));

            return $shop;
        }

        //use shop cookie before detect shop by url
        if ($request->cookies->get('shop')) {
            return $this->loadShop((string) $request->cookies->get('shop'));
        }

        if ($request->attributes->has('_shop')) {
            return $request->attributes->get('_shop');
        }

        if ($request->attributes->has(ShopSubscriber::SHOP_CONTEXT_PROPERTY)) {
            /** @var ShopContext $context */
            $context = $request->attributes->get(ShopSubscriber::SHOP_CONTEXT_PROPERTY);
            return $context->getShop();
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
            return $this->loadShop($paths[$url]['uuid']);
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

        return $this->loadShop($bestMatch['uuid']);
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

            'shop.base_url',
            'shop.hosts',
            'shop.category_id',
            'shop.locale_id',
            'shop.currency_id',
            'shop.customer_group_id',
            'shop.fallback_id',
            'shop.customer_scope',
            'shop.is_default',
            'shop.active',
            "COALESCE(shop.host, main.host, 'localhost') as host",
            "COALESCE(shop.base_path, main.base_path, '') as base_path",
            'COALESCE(shop.secure, main.secure) as secure',
            'COALESCE(shop.shop_template_id, main.shop_template_id) as shop_template_id',
            'COALESCE(shop.document_template_id, main.document_template_id) as document_template_id',
            'COALESCE(shop.payment_method_id, main.payment_method_id) as payment_method_id',
            'COALESCE(shop.shipping_method_id, main.shipping_method_id) as shipping_method_id',
            'COALESCE(shop.area_country_id, main.area_country_id) as area_country_id',
            'COALESCE(shop.tax_calculation_type, main.tax_calculation_type) as tax_calculation_type',
            'locale.locale',
        ]);
        $query->from('shop', 'shop');
        $query->leftJoin('shop', 'shop', 'main', 'main.id = shop.main_id');
        $query->innerJoin('shop', 'locale', 'locale', 'locale.uuid=shop.locale_uuid');

        return $query;
    }

    /**
     * @param $shop
     *
     * @return mixed
     */
    protected function fixUrls($shop)
    {
        $shop['base_url'] = rtrim($shop['base_url'], '/') . '/';
        $shop['base_path'] = rtrim($shop['base_path'], '/') . '/';

        return $shop;
    }

    private function loadShop(string $uuid): ShopBasicStruct
    {
        return $this->shopRepository->read([$uuid], new TranslationContext('', true, null))
            ->get($uuid);
    }
}
