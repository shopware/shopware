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

namespace Shopware\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
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
            return $this->convertShop($paths[$url]);
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

        return $this->convertShop($bestMatch);
    }

    protected function createQuery(): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'shop.id',
            'shop.base_url',
            'shop.hosts',
            'shop.category_id',
            'shop.locale_id',
            'shop.currency_id',
            'shop.customer_group_id',
            'shop.fallback_translation_id',
            'shop.customer_scope',
            'shop.is_default',
            'shop.active',
            'shop.host',
            'shop.base_path',
            'shop.is_secure',
            'locale.code as locale_code',
        ]);
        $query->from('shop', 'shop');
        $query->innerJoin('shop', 'locale', 'locale', 'locale.id = shop.locale_id');

        return $query;
    }

    private function getShopByCookie(Request $request): ?array
    {
        //use shop cookie before detect shop by url
        if (!$request->cookies->has('shop')) {
            return null;
        }

        try {
            $id = Uuid::fromString($request->cookies->get('shop'))->getBytes();
        } catch (InvalidUuidStringException $e) {
            return null;
        }

        $query = $this->createQuery();
        $query->andWhere('shop.id = :id');
        $query->setParameter('id', $id);
        $shop = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($shop) {
            return $this->convertShop($shop);
        }
        $request->cookies->set('shop', null);

        return null;
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

        try {
            $id = Uuid::fromString($request->get('__shop'))->getBytes();
        } catch (InvalidUuidStringException $e) {
            return null;
        }

        $query = $this->createQuery();
        $query->andWhere('shop.id = :id');
        $query->setParameter('id', $id);
        $shop = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($shop) {
            return $this->convertShop($shop);
        }

        return null;
    }

    private function convertShop(array $shop): array
    {
        $shop['id'] = Uuid::fromBytes($shop['id'])->toString();

        if (isset($shop['parent_id'])) {
            $shop['parent_id'] = Uuid::fromBytes($shop['parent_id'])->toString();
        }
        if (isset($shop['category_id'])) {
            $shop['category_id'] = Uuid::fromBytes($shop['category_id'])->toString();
        }
        if (isset($shop['locale_id'])) {
            $shop['locale_id'] = Uuid::fromBytes($shop['locale_id'])->toString();
        }
        if (isset($shop['currency_id'])) {
            $shop['currency_id'] = Uuid::fromBytes($shop['currency_id'])->toString();
        }
        if (isset($shop['customer_group_id'])) {
            $shop['customer_group_id'] = Uuid::fromBytes($shop['customer_group_id'])->toString();
        }
        if (isset($shop['fallback_translation_id'])) {
            $shop['fallback_translation_id'] = Uuid::fromBytes($shop['fallback_translation_id'])->toString();
        }

        return $shop;
    }
}
