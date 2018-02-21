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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Kernel;
use Shopware\Rest\Routing\RouteCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, RequestMatcherInterface
{
    public const SEO_REDIRECT_URL = 'seo_redirect_url';
    public const IS_API_REQUEST_ATTRIBUTE = 'is_api';
    public const REQUEST_TYPE_ATTRIBUTE = '_request_type';
    public const REQUEST_TYPE_STOREFRONT = 'storefront';
    public const REQUEST_TYPE_API = 'api';
    public const REQUEST_TYPE_ADMINISTRATION = 'administration';

    /**
     * Generates an seo URL, e.g. "http://example.com/example-category".
     */
    public const SEO_URL = 5;

    /**
     * @var RequestContext
     */
    private $context;

    /**
     * @var RouteCollection
     */
    private $routes;

    /**
     * @var string
     */
    private $resource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ShopFinder
     */
    private $shopFinder;

    /**
     * @var UrlResolverInterface
     */
    private $urlResolver;

    /**
     * @var \Symfony\Component\HttpKernel\Bundle\BundleInterface[]
     */
    private $bundles;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RouteCollector
     */
    private $apiCollector;

    public function __construct(
        ContainerInterface $container,
        $resource,
        Kernel $kernel,
        ?RequestContext $context = null,
        LoggerInterface $logger = null,
        UrlResolverInterface $urlResolver,
        ShopFinder $shopFinder,
        CacheItemPoolInterface $cache,
        RouteCollector $apiCollector
    ) {
        $this->resource = $resource;
        $this->context = $context;
        $this->logger = $logger;

        $this->bundles = $kernel->getBundles();
        $this->urlResolver = $urlResolver;
        $this->shopFinder = $shopFinder;
        $this->cache = $cache;
        $this->container = $container;
        $this->apiCollector = $apiCollector;
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): ?RequestContext
    {
        return $this->context;
    }

    /**
     * @return RouteCollection
     */
    public function getRouteCollection(): RouteCollection
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        if ($this->container->getParameter('kernel.environment') !== 'prod') {
            $this->routes = $this->loadRoutes();

            return $this->routes;
        }

        $cacheItem = $this->cache->getItem('router_routes');
        if ($routes = $cacheItem->get()) {
            return $this->routes = $routes;
        }

        $this->routes = $this->loadRoutes();
        $cacheItem->set($this->routes);
        $this->cache->save($cacheItem);

        return $this->routes;
    }

    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        $generator = new UrlGenerator(
            $this->getRouteCollection(),
            $this->getContext(),
            $this->logger
        );

        if (!$context = $this->getContext()) {
            return $generator->generate($name, $parameters, $referenceType);
        }

        if (!$shop = $context->getParameter('router_shop')) {
            return $generator->generate($name, $parameters, $referenceType);
        }

        $route = $this->getRouteCollection()->get($name);
        if ($route && $route->getOption('seo') !== true) {
            return $generator->generate($name, $parameters, $referenceType);
        }

        if ($referenceType !== self::SEO_URL) {
            //generate new url with shop base path/url
            $url = $generator->generate($name, $parameters, $referenceType);

            return rtrim($url, '/');
        }

        //rewrite base url for url generator
        $stripBaseUrl = $this->rewriteBaseUrl($shop['base_url'], $shop['base_path']);

        //find seo url for path info
        $pathInfo = $generator->generate($name, $parameters, UrlGenerator::ABSOLUTE_PATH);
        if ($stripBaseUrl !== '/') {
            $pathInfo = str_replace($stripBaseUrl, '', $pathInfo);
        }

        $pathInfo = '/' . trim($pathInfo, '/');

        $fallbackTranslationId = null;
        if ($shop['fallback_translation_id']) {
            $fallbackTranslationId = $shop['fallback_translation_id'];
        }

        $shopContext = new ShopContext(
            $shop['id'],
            [Defaults::CATALOGUE],
            [],
            $shop['currency_id'],
            $shop['locale_id'],
            $fallbackTranslationId,
            Defaults::LIVE_VERSION,
            (float) $shop['currency_factor']
        );


        $seoUrl = $this->urlResolver->getUrl($shop['id'], $pathInfo, $shopContext);

        //generate new url with shop base path/url
        $url = $generator->generate($name, $parameters, $referenceType);

        if ($seoUrl) {
            $seoPathInfo = '/' . ltrim($seoUrl->getSeoPathInfo(), '/');
            $url = str_replace($pathInfo, $seoPathInfo, $url);
        }

        return rtrim($url, '/');
    }

    public function match($pathinfo)
    {
        $pathinfo = '/' . trim($pathinfo, '/');

        $this->context->setPathInfo($pathinfo);

        $matcher = new UrlMatcher($this->getRouteCollection(), $this->getContext());

        return $matcher->match($pathinfo);
    }

    public function matchRequest(Request $request): array
    {
        $requestStack = $this->container->get('request_stack');
        $master = $requestStack->getMasterRequest();

        if ($master !== null && $master->attributes->has('router_shop')) {
            $shop = $master->attributes->get('router_shop');
        } else {
            $shop = $this->shopFinder->findShopByRequest($this->context, $request);
        }

        $pathInfo = $this->context->getPathInfo();

        // save decision which type of request is called (storefront, api, administration)
        $requestType = $this->getRequestType($request);

        $request->attributes->set(self::REQUEST_TYPE_ATTRIBUTE, $requestType);
        $request->attributes->set(
            self::IS_API_REQUEST_ATTRIBUTE,
            in_array($requestType, [self::REQUEST_TYPE_ADMINISTRATION, self::REQUEST_TYPE_API], true)
        );

        if (!$shop) {
            return $this->match($pathInfo);
        }

        //save detected shop to context for further processes
        $currencyId = $this->getCurrencyId($request, $shop['currency_id']);

        $this->context->setParameter('router_shop', $shop);
        $request->attributes->set('router_shop', $shop);
        $request->attributes->set('_shop_id', $shop['id']);
        $request->attributes->set('_currency_id', $currencyId);
        $request->attributes->set('_locale_id', $shop['locale_id']);
        $request->setLocale($shop['locale_code']);

        $stripBaseUrl = $this->rewriteBaseUrl($shop['base_url'], $shop['base_path']);

        // strip base url from path info
        $pathInfo = $request->getBaseUrl() . $request->getPathInfo();
        $pathInfo = preg_replace('#^' . $stripBaseUrl . '#i', '', $pathInfo);
        $pathInfo = '/' . trim($pathInfo, '/');

        $fallbackTranslationId = null;
        if ($shop['fallback_translation_id']) {
            $fallbackTranslationId = $shop['fallback_translation_id'];
        }

        $shopContext = new ShopContext(
            $shop['id'],
            [Defaults::CATALOGUE],
            [],
            $shop['currency_id'],
            $shop['locale_id'],
            $fallbackTranslationId,
            Defaults::LIVE_VERSION,
            (float) $shop['currency_factor']
        );

        if (strpos($pathInfo, '/widgets/') !== false) {
            return $this->match($pathInfo);
        }

        if ($request->attributes->get(self::IS_API_REQUEST_ATTRIBUTE)) {
            return $this->match($pathInfo);
        }

        //resolve seo urls to use symfony url matcher for route detection
        $seoUrl = $this->urlResolver->getPathInfo($shop['id'], $pathInfo, $shopContext);

        if (!$seoUrl) {
            return $this->match($pathInfo);
        }

        $pathInfo = $seoUrl->getPathInfo();
        if (!$seoUrl->getIsCanonical()) {
            $redirectUrl = $this->urlResolver->getUrl($shop['id'], $seoUrl->getPathInfo(), $shopContext);
            $request->attributes->set(self::SEO_REDIRECT_URL, $redirectUrl);
        }

        return $this->match($pathInfo);
    }

    public function assemble(string $url): string
    {
        $generator = new UrlGenerator(
            $this->getRouteCollection(),
            $this->getContext(),
            $this->logger
        );

        $base = $generator->generate('homepage', [], UrlGenerator::ABSOLUTE_URL);

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    protected function getCurrencyId(Request $request, string $fallback): string
    {
        if ($this->context->getMethod() === 'POST' && $request->get('__currency')) {
            return (string) $request->get('__currency');
        }

        if ($request->cookies->has('currency')) {
            return (string) $request->cookies->get('currency');
        }

        if ($request->attributes->has('_currency_id')) {
            return (string) $request->attributes->get('_currency_id');
        }

        return $fallback;
    }

    private function loadRoutes(): RouteCollection
    {
        $routeCollection = new RouteCollection();

        if (file_exists($this->resource)) {
            $routeCollection->addCollection(
                $this->container->get('routing.loader')->load($this->resource)
            );
        }

        foreach ($this->bundles as $bundle) {
            if (!file_exists($bundle->getPath() . '/Controller')) {
                continue;
            }

            $routeCollection->addCollection(
                $this->container->get('routing.loader')->import($bundle->getPath() . '/Controller/', 'annotation')
            );
        }

        $routeCollection->addCollection(
            $this->apiCollector->collect()
        );

        return $routeCollection;
    }

    private function rewriteBaseUrl(?string $baseUrl, string $basePath): string
    {
        //generate new path info for detected shop
        $stripBaseUrl = $baseUrl ?? $basePath;
        $stripBaseUrl = rtrim($stripBaseUrl, '/') . '/';

        //rewrite base url for url generator
        $this->context->setBaseUrl(rtrim($stripBaseUrl, '/'));

        return $stripBaseUrl;
    }

    private function getRequestType(Request $request): string
    {
        $isApi = stripos($request->getPathInfo(), '/api/') === 0;

        if ($isApi && $request->query->has('administration')) {
            return self::REQUEST_TYPE_ADMINISTRATION;
        }
        if ($isApi) {
            return self::REQUEST_TYPE_API;
        }

        return self::REQUEST_TYPE_STOREFRONT;
    }
}
