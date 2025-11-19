<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannelDomain;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpWhitelist: string, locale: string, themeName: string, parentThemeName: string}
 */
#[Package('framework')]
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @return array<string, Domain>
     */
    abstract public function load(): array;

    /**
     * @return Domain|null
     */
    public function findDomain(Request $request): ?array
    {
        $domains = $this->load();

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
        $domains = array_filter($domains, fn ($baseUrl): bool => str_starts_with($requestUrl, $baseUrl), \ARRAY_FILTER_USE_KEY);

        if (empty($domains)) {
            return null;
        }

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = current($domains);
        foreach ($domains as $baseUrl => $urlConfig) {
            if (mb_strlen($baseUrl) > mb_strlen($lastBaseUrl)) {
                $bestMatch = $urlConfig;
                $lastBaseUrl = $baseUrl;
            }
        }

        $bestMatch['url'] = rtrim($bestMatch['url'], '/');

        return $bestMatch;
    }

    private function getSchemeAndHttpHost(Request $request): string
    {
        return $request->getScheme() . '://' . idn_to_utf8($request->getHttpHost());
    }
}
