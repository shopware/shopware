<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Seo\SeoResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestTransformer
{
    /**
     * @var Connection
     */
    private $connection;

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

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function transform(SymfonyRequest $request): SymfonyRequest
    {
        if (!$this->isSalesChannelRequired($request->getPathInfo())) {
            return $request;
        }

        $salesChannel = $this->findSalesChannel($request);
        if ($salesChannel === null) {
            return $request;
        }

        $baseUrl = str_replace($request->getSchemeAndHttpHost(), '', $salesChannel['url']);

        $resolved = $this->resolveSeoUrl($request, $baseUrl, $salesChannel['salesChannelId']);

        $server = array_merge(
            $_SERVER,
            [
                'REQUEST_URI' => $baseUrl . $resolved['pathInfo'],
                'SCRIPT_NAME' => $baseUrl . '/index.php',
                'SCRIPT_FILENAME' => $baseUrl . '/index.php',
            ]
        );

        $clone = $request->duplicate(null, null, null, null, null, $server);

        $clone->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannel['salesChannelId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $salesChannel['locale']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $salesChannel['snippetSetId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $salesChannel['currencyId']);
        $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, $salesChannel['id']);

        if (isset($resolved['canonicalPathInfo'])) {
            $clone->attributes->set(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK, $request->getSchemeAndHttpHost() . $baseUrl . $resolved['canonicalPathInfo']);
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

    private function findSalesChannel(SymfonyRequest $request): ?array
    {
        /** @var Statement $statement */
        $statement = $this->connection->createQueryBuilder()
            ->select([
                'domain.url `key`',
                'domain.url',
                'LOWER(HEX(domain.id)) id',
                'LOWER(HEX(sales_channel.id)) salesChannelId',
                'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
                'LOWER(HEX(domain.currency_id)) currencyId',
                'LOWER(HEX(domain.language_id)) languageId',
                'snippet_set.iso as locale',
            ])->from('sales_channel')
            ->innerJoin('sales_channel', 'sales_channel_domain', 'domain', 'domain.sales_channel_id = sales_channel.id')
            ->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id')
            ->where('sales_channel.type_id = UNHEX(:typeId)')
            ->andWhere('sales_channel.active')
            ->setParameter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT)
            ->execute();
        $domains = FetchModeHelper::groupUnique($statement->fetchAll());

        if (empty($domains)) {
            return null;
        }

        $requestUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath() . $request->getPathInfo(), '/');

        // direct hit
        if (array_key_exists($requestUrl, $domains)) {
            return $domains[$requestUrl];
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

        return $bestMatch;
    }

    private function resolveSeoUrl(Request $request, string $baseUrl, string $salesChannelId): array
    {
        $seoPathInfo = $request->getPathInfo();
        if (!empty($baseUrl) && strpos($seoPathInfo, $baseUrl) === 0) {
            $seoPathInfo = substr($seoPathInfo, strlen($baseUrl));
        }

        if ($_ENV['FEATURE_NEXT_741'] ?? false) {
            return (new SeoResolver($this->connection))->resolveSeoPath($salesChannelId, $seoPathInfo);
        }

        return ['pathInfo' => $request->getPathInfo()];
    }
}
