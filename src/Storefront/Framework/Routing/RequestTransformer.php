<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Symfony\Component\HttpFoundation\Request;
use TrueBV\Punycode;

class RequestTransformer implements RequestTransformerInterface
{
    public const REQUEST_TRANSFORMER_CACHE_KEY = CachedDomainLoader::CACHE_KEY;

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

    public const ORIGINAL_REQUEST_URI = 'sw-original-request-uri';

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

        SalesChannelRequest::ATTRIBUTE_CSRF_PROTECTED,
    ];

    /**
     * @var RequestTransformerInterface
     */
    private $decorated;

    /**
     * @var string[]
     */
    private array $whitelist = [
        '/_wdt/',
        '/_profiler/',
        '/_error/',
        '/payment/finalize-transaction',
    ];

    /**
     * @var Punycode
     */
    private $punycode;

    /**
     * @var AbstractSeoResolver
     */
    private $resolver;

    /**
     * @var array
     */
    private $registeredApiPrefixes;

    /**
     * @var AbstractDomainLoader
     */
    private $domainLoader;

    public function __construct(
        RequestTransformerInterface $decorated,
        AbstractSeoResolver $resolver,
        array $registeredApiPrefixes,
        AbstractDomainLoader $domainLoader
    ) {
        $this->decorated = $decorated;
        $this->resolver = $resolver;
        $this->punycode = new Punycode();
        $this->registeredApiPrefixes = $registeredApiPrefixes;
        $this->domainLoader = $domainLoader;
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

        $currentRequestUri = $request->getRequestUri();

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
            $urlPath = parse_url($salesChannel['url'], \PHP_URL_PATH);
            if ($urlPath === false || $urlPath === null) {
                $urlPath = '';
            }

            $baseUrlPath = trim($urlPath, '/');
            if (\strlen($baseUrlPath) > 1 && strpos($baseUrlPath, '/') !== 0) {
                $baseUrlPath = '/' . $baseUrlPath;
            }

            $transformedRequest->attributes->set(
                SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK,
                $this->getSchemeAndHttpHost($request) . $baseUrlPath . $resolved['canonicalPathInfo']
            );
        }

        $transformedRequest->headers->add($request->headers->all());
        $transformedRequest->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $salesChannel['languageId']);
        $transformedRequest->attributes->set(self::ORIGINAL_REQUEST_URI, $currentRequestUri);

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
        $domains = $this->domainLoader->load();

        if (empty($domains)) {
            return null;
        }

        // domain urls and request uri should be in same format, all with trailing slash
        $requestUrl = rtrim($this->getSchemeAndHttpHost($request) . $request->getBasePath() . $request->getPathInfo(), '/') . '/';

        // direct hit
        if (\array_key_exists($requestUrl, $domains)) {
            $domain = $domains[$requestUrl];
            $domain['url'] = rtrim($domain['url'], '/');

            return $domain;
        }

        // reduce shops to which base url is the beginning of the request
        $domains = array_filter($domains, function ($baseUrl) use ($requestUrl) {
            return mb_strpos($requestUrl, $baseUrl) === 0;
        }, \ARRAY_FILTER_USE_KEY);

        if (empty($domains)) {
            return null;
        }

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = current($domains);
        /** @var string $baseUrl */
        foreach ($domains as $baseUrl => $urlConfig) {
            if (mb_strlen($baseUrl) > mb_strlen($lastBaseUrl)) {
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

        $resolved = $this->resolver->resolve($languageId, $salesChannelId, $seoPathInfo);

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
}
