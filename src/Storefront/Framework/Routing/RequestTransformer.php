<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Shopware\Core\Content\Seo\SeoResolverInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopware\Storefront\Theme\ThemeDefinition;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\ItemInterface;
use TrueBV\Punycode;

class RequestTransformer implements RequestTransformerInterface
{
    public const REQUEST_TRANSFORMER_CACHE_KEY = 'request_transformer_domains';

    /**
     * Virtual path of the "domain"
     *
     * @example
     * - `/de`
     * - `/en`
     * - {empty} - the virtual path is optional
     */
    public const SALES_CHANNEL_BASE_URL = 'sw-sales-channel-base-url';

    /**
     * Scheme + Host + port + subdir in web root
     *
     * @example
     * - `https://shop.example` - no subdir
     * - `http://localhost:8000/subdir` - with sub dir `/subdir`
     */
    public const SALES_CHANNEL_ABSOLUTE_BASE_URL = 'sw-sales-channel-absolute-base-url';

    /**
     * Scheme + Host + port + subdir in web root + virtual path
     *
     * @example
     * - `https://shop.example` - no sub dir and no virtual path
     * - `https://shop.example/en` - no sub dir and virtual path `/en`
     * - `http://localhost:8000/subdir` - with sub directory `/subdir`
     * - `http://localhost:8000/subdir/de` - with sub directory `/subdir` and virtual path `/de`
     */
    public const STOREFRONT_URL = 'sw-storefront-url';

    public const SALES_CHANNEL_RESOLVED_URI = 'resolved-uri';

    private const INHERITABLE_ATTRIBUTE_NAMES = [
        self::SALES_CHANNEL_BASE_URL,
        self::SALES_CHANNEL_ABSOLUTE_BASE_URL,
        self::STOREFRONT_URL,
        self::SALES_CHANNEL_RESOLVED_URI,

        PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID,
        SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST,

        SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID,
        SalesChannelRequest::ATTRIBUTE_DOMAIN_ID,

        SalesChannelRequest::ATTRIBUTE_THEME_ID,
        SalesChannelRequest::ATTRIBUTE_THEME_NAME,
        SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME,

        SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK,
    ];

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
    ];

    /**
     * @var Punycode
     */
    private $punycode;

    /**
     * @var SeoResolverInterface
     */
    private $resolver;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var array
     */
    private $registeredApiPrefixes;

    public function __construct(
        RequestTransformerInterface $decorated,
        Connection $connection,
        SeoResolverInterface $resolver,
        TagAwareAdapterInterface $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        array $registeredApiPrefixes
    ) {
        $this->connection = $connection;
        $this->decorated = $decorated;
        $this->resolver = $resolver;
        $this->punycode = new Punycode();
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->registeredApiPrefixes = $registeredApiPrefixes;
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

        $resolved = $this->resolveSeoUrl(
            $request,
            $baseUrl,
            $salesChannel['languageId'],
            $salesChannel['salesChannelId']
        );

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

        $transformedRequest = $request->duplicate(null, null, null, null, null, $transformedServerVars);
        $transformedRequest->attributes->set(self::SALES_CHANNEL_BASE_URL, $baseUrl);
        $transformedRequest->attributes->set(self::SALES_CHANNEL_ABSOLUTE_BASE_URL, rtrim($absoluteBaseUrl, '/'));
        $transformedRequest->attributes->set(
            self::STOREFRONT_URL,
            $transformedRequest->attributes->get(self::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $transformedRequest->attributes->get(self::SALES_CHANNEL_BASE_URL)
        );
        $transformedRequest->attributes->set(self::SALES_CHANNEL_RESOLVED_URI, $resolved['pathInfo']);

        $transformedRequest->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannel['salesChannelId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $salesChannel['locale']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $salesChannel['snippetSetId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $salesChannel['currencyId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, $salesChannel['id']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $salesChannel['themeId']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, $salesChannel['themeName']);
        $transformedRequest->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, $salesChannel['parentThemeName']);

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE,
            (bool) $salesChannel['maintenance']
        );

        $transformedRequest->attributes->set(
            SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST,
            $salesChannel['maintenanceIpWhitelist']
        );

        if (isset($resolved['canonicalPathInfo'])) {
            $transformedRequest->attributes->set(
                SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK,
                $this->getSchemeAndHttpHost($request) . $baseUrl . $resolved['canonicalPathInfo']
            );
        }

        $transformedRequest->headers->add($request->headers->all());
        $transformedRequest->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $salesChannel['languageId']);

        return $transformedRequest;
    }

    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        $inheritableAttributes = $this->decorated
            ->extractInheritableAttributes($sourceRequest);

        foreach (self::INHERITABLE_ATTRIBUTE_NAMES as $attributeName) {
            if (!$sourceRequest->attributes->has($attributeName)) {
                continue;
            }

            $inheritableAttributes[$attributeName] = $sourceRequest->attributes->get($attributeName);
        }

        return $inheritableAttributes;
    }

    private function isSalesChannelRequired(string $pathInfo): bool
    {
        $pathInfo = rtrim($pathInfo, '/') . '/';

        foreach ($this->registeredApiPrefixes as $apiPrefix) {
            if (mb_strpos($pathInfo, '/' . $apiPrefix . '/') === 0) {
                return false;
            }
        }

        foreach ($this->whitelist as $prefix) {
            if (mb_strpos($pathInfo, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }

    private function findSalesChannel(Request $request): ?array
    {
        $domains = $this->fetchDomains();

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
            return mb_strpos($requestUrl, $baseUrl) === 0;
        }, ARRAY_FILTER_USE_KEY);

        if (empty($domains)) {
            return null;
        }

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = current($domains);
        /** @var string $baseUrl */
        foreach ($domains as $baseUrl => $urlConfig) {
            if (\mb_strlen($baseUrl) > \mb_strlen($lastBaseUrl)) {
                $bestMatch = $urlConfig;
            }

            $lastBaseUrl = $baseUrl;
        }

        $bestMatch['url'] = rtrim($bestMatch['url'], '/');

        return $bestMatch;
    }

    private function resolveSeoUrl(Request $request, string $baseUrl, string $languageId, string $salesChannelId): array
    {
        $seoPathInfo = $request->getPathInfo();

        // only remove full base url not part
        // registered domain: 'shop-dev.de/de'
        // incoming request:  'shop-dev.de/detail'
        // without leading slash, detail would be stripped
        $baseUrl = rtrim($baseUrl, '/') . '/';

        if ($this->equalsBaseUrl($seoPathInfo, $baseUrl)) {
            $seoPathInfo = '';
        } elseif ($this->containsBaseUrl($seoPathInfo, $baseUrl)) {
            $seoPathInfo = mb_substr($seoPathInfo, mb_strlen($baseUrl));
        }

        $resolved = $this->resolver->resolveSeoPath($languageId, $salesChannelId, $seoPathInfo);

        $resolved['pathInfo'] = '/' . ltrim($resolved['pathInfo'], '/');

        return $resolved;
    }

    private function getSchemeAndHttpHost(Request $request): string
    {
        return $request->getScheme() . '://' . $this->punycode->decode($request->getHttpHost());
    }

    /**
     * We add the trailing slash to the base url
     * so we have to add it to the path info too, to check if they are equal
     */
    private function equalsBaseUrl(string $seoPathInfo, string $baseUrl): bool
    {
        return $baseUrl === rtrim($seoPathInfo, '/') . '/';
    }

    /**
     * We don't have to add the trailing slash when we check if the pathInfo contains teh base url
     */
    private function containsBaseUrl(string $seoPathInfo, string $baseUrl): bool
    {
        return !empty($baseUrl) && mb_strpos($seoPathInfo, $baseUrl) === 0;
    }

    private function fetchDomains(): array
    {
        $item = $this->cache->getItem(self::REQUEST_TRANSFORMER_CACHE_KEY);

        if ($item->isHit() && $item->get()) {
            return $item->get();
        }

        /** @var Statement $statement */
        $statement = $this->connection->createQueryBuilder()
            ->select(
                [
                    'CONCAT(TRIM(TRAILING "/" FROM domain.url), "/") `key`',
                    'CONCAT(TRIM(TRAILING "/" FROM domain.url), "/") url',
                    'LOWER(HEX(domain.id)) id',
                    'LOWER(HEX(sales_channel.id)) salesChannelId',
                    'LOWER(HEX(sales_channel.type_id)) typeId',
                    'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
                    'LOWER(HEX(domain.currency_id)) currencyId',
                    'LOWER(HEX(domain.language_id)) languageId',
                    'LOWER(HEX(theme.id)) themeId',
                    'sales_channel.maintenance maintenance',
                    'sales_channel.maintenance_ip_whitelist maintenanceIpWhitelist',
                    'snippet_set.iso as locale',
                    'theme.technical_name as themeName',
                    'parentTheme.technical_name as parentThemeName',
                ]
            )->from('sales_channel')
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

        $tags = [
            SalesChannelDefinition::ENTITY_NAME . '.id',
            SalesChannelDefinition::ENTITY_NAME . '.type_id',
            SalesChannelDefinition::ENTITY_NAME . '.maintenance',
            SalesChannelDefinition::ENTITY_NAME . '.maintenance_ip_whitelist',
            SalesChannelDomainDefinition::ENTITY_NAME . '.id',
            SalesChannelDomainDefinition::ENTITY_NAME . '.url',
            SalesChannelDomainDefinition::ENTITY_NAME . '.snippet_set_id',
            SalesChannelDomainDefinition::ENTITY_NAME . '.currency_id',
            SalesChannelDomainDefinition::ENTITY_NAME . '.language_id',
            SnippetSetDefinition::ENTITY_NAME . '.iso',
            ThemeDefinition::ENTITY_NAME . '.id',
            ThemeDefinition::ENTITY_NAME . '.technical_name',
        ];

        foreach ($domains as $domain) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($domain['salesChannelId'], SalesChannelDefinition::ENTITY_NAME);
            $tags[] = $this->cacheKeyGenerator->getEntityTag($domain['id'], SalesChannelDomainDefinition::ENTITY_NAME);
        }
        $tags = array_unique($tags);

        $item->set($domains);
        if ($item instanceof ItemInterface) {
            $item->tag($tags);
        }
        $this->cache->save($item);

        return $domains;
    }
}
