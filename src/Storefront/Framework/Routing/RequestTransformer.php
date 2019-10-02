<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopware\Storefront\Framework\Seo\SeoResolver;
use Symfony\Component\HttpFoundation\Request;
use TrueBV\Punycode;

class RequestTransformer implements RequestTransformerInterface
{
    public const SALES_CHANNEL_BASE_URL = 'sw-sales-channel-base-url';
    public const SALES_CHANNEL_ABSOLUTE_BASE_URL = 'sw-sales-channel-absolute-base-url';
    public const SALES_CHANNEL_RESOLVED_URI = 'resolved-uri';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RequestTransformerInterface
     */
    private $decorated;

    /**
     * @var string[]
     */
    private $whitelist = [
        '/_wdt/',
        '/_profiler/',
        '/_error/',
        '/api/',
        '/sales-channel-api/',
        '/admin/',
    ];

    /**
     * @var Punycode
     */
    private $punycode;

    public function __construct(RequestTransformerInterface $decorated, Connection $connection)
    {
        $this->connection = $connection;
        $this->decorated = $decorated;
        $this->punycode = new Punycode();
    }

    public function transform(Request $request): Request
    {
        $request = $this->decorated->transform($request);

        if (!$this->isSalesChannelRequired($request->getPathInfo())) {
            return $this->decorated->transform($request);
        }

        $salesChannel = $this->findSalesChannel($request);
        if ($salesChannel === null) {
            // this class and therefore the "isSalesChannelRequired" method is currently not extendable
            // which can cause problems when adding custom paths
            throw new SalesChannelMappingException($request->getUri());
        }

        $absoluteBaseUrl = $this->getSchemeAndHttpHost($request) . $request->getBaseUrl();
        $baseUrl = str_replace($absoluteBaseUrl, '', $salesChannel['url']);

        $resolved = $this->resolveSeoUrl($request, $baseUrl, $salesChannel['languageId'], $salesChannel['salesChannelId']);

        /**
         * - Remove "virtual" suffix of domain mapping shopware.de/de
         * - To get only the host shopware.de as real request uri shopware.de/
         * - Resolve remaining seo url and get the real path info shopware.de/outdoor => shopware.de/navigation/{id}
         *
         * Possible domains
         *
         * same host, different "virtual" suffix
         * http://shopware.de/de
         * http://shopware.de/en
         * http://shopware.de/fr
         *
         * same host, different location
         * http://shopware.fr
         * http://shopware.com
         * http://shopware.de
         *
         * complete different host and location
         * http://color.com
         * http://farben.de
         * http://couleurs.fr
         *
         * installation in sub directory
         * http://localhost/development/public/de
         * http://localhost/development/public/en
         * http://localhost/development/public/fr
         *
         * installation with port
         * http://localhost:8080
         * http://localhost:8080/en
         * http://localhost:8080/fr
         */
        $transformedServerVars = array_merge(
            $request->server->all(),
            ['REQUEST_URI' => rtrim($request->getBaseUrl(), '/') . $resolved['pathInfo']]
        );

        $clone = $request->duplicate(null, null, null, null, null, $transformedServerVars);

        $clone->attributes->set(self::SALES_CHANNEL_BASE_URL, $baseUrl);
        $clone->attributes->set(self::SALES_CHANNEL_ABSOLUTE_BASE_URL, rtrim($absoluteBaseUrl, '/'));
        $clone->attributes->set(self::SALES_CHANNEL_RESOLVED_URI, $resolved['pathInfo']);

        $clone->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannel['salesChannelId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $salesChannel['locale']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $salesChannel['snippetSetId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $salesChannel['currencyId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, $salesChannel['id']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $salesChannel['themeId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, $salesChannel['themeName']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, $salesChannel['parentThemeName']);

        if (isset($resolved['canonicalPathInfo'])) {
            $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK, $this->getSchemeAndHttpHost($request) . $baseUrl . $resolved['canonicalPathInfo']);
        }

        $clone->headers->add($request->headers->all());
        $clone->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $salesChannel['languageId']);

        return $clone;
    }

    private function isSalesChannelRequired(string $pathInfo): bool
    {
        $pathInfo = rtrim($pathInfo, '/') . '/';

        foreach ($this->whitelist as $prefix) {
            if (strpos($pathInfo, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }

    private function findSalesChannel(Request $request): ?array
    {
        /** @var Statement $statement */
        $statement = $this->connection->createQueryBuilder()
            ->select([
                'CONCAT(TRIM(TRAILING "/" FROM domain.url), "/") `key`',
                'CONCAT(TRIM(TRAILING "/" FROM domain.url), "/") url',
                'LOWER(HEX(domain.id)) id',
                'LOWER(HEX(sales_channel.id)) salesChannelId',
                'LOWER(HEX(sales_channel.type_id)) typeId',
                'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
                'LOWER(HEX(domain.currency_id)) currencyId',
                'LOWER(HEX(domain.language_id)) languageId',
                'snippet_set.iso as locale',
                'LOWER(HEX(theme.id)) themeId',
                'theme.technical_name as themeName',
                'parentTheme.technical_name as parentThemeName',
            ])->from('sales_channel')
            ->innerJoin('sales_channel', 'sales_channel_domain', 'domain', 'domain.sales_channel_id = sales_channel.id')
            ->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id')
            ->leftJoin('sales_channel', 'theme_sales_channel', 'theme_sales_channel', 'sales_channel.id = theme_sales_channel.sales_channel_id')
            ->leftJoin('theme_sales_channel', 'theme', 'theme', 'theme_sales_channel.theme_id = theme.id')
            ->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id')
            ->where('sales_channel.type_id = UNHEX(:typeId)')
            ->andWhere('sales_channel.active')
            ->setParameter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT)
            ->execute();
        $domains = FetchModeHelper::groupUnique($statement->fetchAll());

        if (empty($domains)) {
            return null;
        }

        // domain urls and request uri should be in same format, all with trailing slash
        $requestUrl = rtrim($this->getSchemeAndHttpHost($request) . $request->getBasePath() . $request->getPathInfo(), '/') . '/';

        // direct hit
        if (array_key_exists($requestUrl, $domains)) {
            $domain = $domains[$requestUrl];
            $domain['url'] = rtrim($domain['url'], '/');

            return $domain;
        }

        // reduce shops to which base url is the beginning of the request
        $domains = array_filter($domains, function ($baseUrl) use ($requestUrl) {
            return strpos($requestUrl, $baseUrl) === 0;
        }, ARRAY_FILTER_USE_KEY);

        if (empty($domains)) {
            return null;
        }

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = current($domains);
        /** @var string $baseUrl */
        foreach ($domains as $baseUrl => $urlConfig) {
            if (\strlen($baseUrl) > \strlen($lastBaseUrl)) {
                $bestMatch = $urlConfig;
            }

            $lastBaseUrl = $baseUrl;
        }

        $bestMatch['url'] = rtrim($bestMatch['url'], '/');

        return $bestMatch;
    }

    private function resolveSeoUrl(Request $request, string $baseUrl, string $languageId, string $salesChannelId): array
    {
        $seoPathInfo = rtrim($request->getPathInfo(), '/') . '/';

        // only remove full base url not part
        // registered domain: 'shop-dev.de/de'
        // incoming request:  'shop-dev.de/detail'
        // without leading slash, detail would be stripped

        $baseUrl = rtrim($baseUrl, '/') . '/';

        if (!empty($baseUrl) && strpos($seoPathInfo, $baseUrl) === 0) {
            $seoPathInfo = substr($seoPathInfo, strlen($baseUrl));
        }

        $resolved = (new SeoResolver($this->connection))
            ->resolveSeoPath($languageId, $salesChannelId, $seoPathInfo);

        $resolved['pathInfo'] = '/' . trim($resolved['pathInfo'], '/');

        return $resolved;
    }

    private function getSchemeAndHttpHost(Request $request): string
    {
        return $request->getScheme() . '://' . $this->punycode->decode($request->getHttpHost());
    }
}
