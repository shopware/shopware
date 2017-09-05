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

use Psr\Log\LoggerInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Storefront\Session\ShopSubscriber;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, RequestMatcherInterface
{
    const SEO_REDIRECT_URL = 'seo_redirect_url';

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
     * @var Loader
     */
    private $routingLoader;

    /**
     * @var \Symfony\Component\HttpKernel\Bundle\BundleInterface[]
     */
    private $bundles;

    public function __construct(
        $resource,
        \AppKernel $kernel,
        ?RequestContext $context = null,
        LoggerInterface $logger = null,
        UrlResolverInterface $urlResolver,
        ShopFinder $shopFinder,
        LoaderInterface $routingLoader
    ) {
        $this->resource = $resource;
        $this->context = $context;
        $this->logger = $logger;

        $this->bundles = $kernel->getBundles();
        $this->urlResolver = $urlResolver;
        $this->shopFinder = $shopFinder;
        $this->routingLoader = $routingLoader;
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
        if (null === $this->routes) {
            $this->routes = $this->loadRoutes();
        }

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

        /** @var ShopDetailStruct $shop */
        if (!$shop = $context->getParameter('shop')) {
            return $generator->generate($name, $parameters, $referenceType);
        }

        //rewrite base url for url generator
        $stripBaseUrl = $this->rewriteBaseUrl($shop->getBaseUrl(), $shop->getBasePath());

        $route = $this->getRouteCollection()->get($name);
        if ($route->getOption('seo') !== true) {
            return $generator->generate($name, $parameters, $referenceType);
        }

        //find seo url for path info
        $pathinfo = $generator->generate($name, $parameters, UrlGenerator::ABSOLUTE_PATH);
        $pathinfo = str_replace($stripBaseUrl, '', $pathinfo);
        $pathinfo = '/' . trim($pathinfo, '/');

        $seoUrl = $this->urlResolver->getUrl(
            $shop->getUuid(),
            $pathinfo,
            TranslationContext::createFromShop($shop)
        );

        //generate new url with shop base path/url
        $url = $generator->generate($name, $parameters, $referenceType);

        if ($seoUrl) {
            $url = str_replace($pathinfo, $seoUrl->getSeoPathInfo(), $url);
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
        $shop = $this->shopFinder->findShopByRequest($this->context, $request);
        $pathinfo = $this->context->getPathInfo();

        if (!$shop) {
            return $this->match($pathinfo);
        }

        //save detected shop to context for further processes
        $this->context->setParameter('shop', $shop);
        $request->attributes->set('_shop', $shop);

        $currencyUuid = $this->getCurrencyUuid($request, $shop->getCurrencyUuid());

        $request->attributes->set('_shop_uuid', $shop->getUuid());
        $request->attributes->set('_currency_uuid', $currencyUuid);

        //set shop locale
        $request->setLocale($shop->getLocale()->getLocale());

        $stripBaseUrl = $this->rewriteBaseUrl($shop->getBaseUrl(), $shop->getBasePath());

        // strip base url from path info
        $pathinfo = $request->getBaseUrl() . $request->getPathInfo();
        $pathinfo = preg_replace('#^' . $stripBaseUrl . '#i', '', $pathinfo);
        $pathinfo = '/' . trim($pathinfo, '/');

        $translationContext = TranslationContext::createFromShop($shop);

        //resolve seo urls to use symfony url matcher for route detection
        $seoUrl = $this->urlResolver->getPathInfo($shop->getUuid(), $pathinfo, $translationContext);

        if (!$seoUrl) {
            return $this->match($pathinfo);
        }

        $pathinfo = $seoUrl->getPathInfo();
        if (!$seoUrl->getIsCanonical()) {
            $redirectUrl = $this->urlResolver->getUrl($shop->getUuid(), $seoUrl->getPathInfo(), $translationContext);
            $request->attributes->set(self::SEO_REDIRECT_URL, $redirectUrl->getSeoPathInfo());
        }

        return $this->match($pathinfo);
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

    protected function getCurrencyUuid(Request $request, string $currencyUuid): string
    {
        if ($this->context->getMethod() === 'POST' && $request->get('__currency')) {
            return (string) $request->get('__currency');
        }

        if ($request->cookies->has('currency')) {
            return (string) $request->cookies->get('currency');
        }

        if ($request->attributes->has('_currency_uuid')) {
            return (string) $request->attributes->get('_currency_uuid');
        }

        if ($request->attributes->has(ShopSubscriber::SHOP_CONTEXT_PROPERTY)) {
            /** @var ShopContext $context */
            $context = $request->attributes->get(ShopSubscriber::SHOP_CONTEXT_PROPERTY);

            return $context->getCurrency()->getUuid();
        }

        return $currencyUuid;
    }

    private function loadRoutes(): RouteCollection
    {
        $routeCollection = new RouteCollection();

        if (file_exists($this->resource)) {
            $routeCollection->addCollection(
                $this->routingLoader->load($this->resource)
            );
        }

        foreach ($this->bundles as $bundle) {
            if (!file_exists($bundle->getPath() . '/Controller')) {
                continue;
            }

            $routeCollection->addCollection(
                $this->routingLoader->import($bundle->getPath() . '/Controller/', 'annotation')
            );
        }

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
}
