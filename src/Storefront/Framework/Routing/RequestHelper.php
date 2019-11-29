<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use TrueBV\Punycode;

class RequestHelper
{
    /**
     * @var Punycode
     */
    private $punycode;

    public function __construct()
    {
        $this->punycode = new Punycode();
    }

    public function setBaseUrlAndPathInfo(Request $request, string $baseUrl, string $pathInfo)
    {
        $closure = Closure::bind(function ($request, $baseUrl, $pathInfo) {
            $request->requestUri = $baseUrl . $pathInfo;
            $request->baseUrl = $baseUrl;
            $request->pathInfo = $pathInfo;
        }, null, $request);
        $closure($request, $baseUrl, $pathInfo);
    }

    public function getSeoPathInfo(string $seoPathInfo, string $baseUrl)
    {
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

        return $seoPathInfo;
    }

    public function getSchemeAndHttpHost(Request $request): string
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
