<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
/**
 * @internal
 */
class CacheHashService
{
    /**
     * @param array<string> $cookies
     *
     * @internal
     */
    public function __construct(
        private readonly ExtensionDispatcher $extensions,
        private readonly CacheRelevantRulesResolver $ruleResolver,
        private readonly array $cookies,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function applyCacheHash(Request $request, SalesChannelContext $context, Cart $cart, Response $response): void
    {
        if ($request->headers->has(PlatformRequest::HEADER_CURRENCY_ID)) {
            $response->headers->set(
                PlatformRequest::HEADER_CURRENCY_ID,
                $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID)
            );
        }

        if ($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            $response->headers->set(
                PlatformRequest::HEADER_LANGUAGE_ID,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID)
            );
        }

        $newVaryArray = array_merge($response->getVary(), [
            HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
            PlatformRequest::HEADER_CURRENCY_ID,
            PlatformRequest::HEADER_LANGUAGE_ID,
        ]);
        $newVaryArray = array_unique(array_map(fn (string $v) => \trim($v), $newVaryArray));

        $response->setVary($newVaryArray);

        $isCacheHashRequired = $this->extensions->publish(
            CacheHashRequiredExtension::NAME,
            new CacheHashRequiredExtension($request, $context, $cart),
            $this->isCacheHashRequired(...),
        );

        if (!$isCacheHashRequired) {
            if ($request->cookies->has(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE)) {
                $response->headers->removeCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
                $response->headers->clearCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
            }

            return;
        }

        $newValue = $this->buildCacheHash($request, $context);

        if ($request->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, '') !== $newValue) {
            $cookie = Cookie::create(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $newValue);
            $cookie->setSecureDefault($request->isSecure());

            $response->headers->setCookie($cookie);
        }

        $response->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $newValue);
    }

    private function buildCacheHash(Request $request, SalesChannelContext $context): string
    {
        $ruleAreas = $this->ruleResolver->resolveRuleAreas($request, $context);

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            $ruleIds = $context->getRuleIdsByAreas($ruleAreas);
        } else {
            $ruleIds = $context->getRuleIds();
        }

        $ruleIds = array_unique($ruleIds);
        sort($ruleIds);

        $parts = [
            HttpCacheCookieEvent::RULE_IDS => $ruleIds,
            HttpCacheCookieEvent::VERSION_ID => $context->getVersionId(),
            HttpCacheCookieEvent::CURRENCY_ID => $context->getCurrencyId(),
            HttpCacheCookieEvent::LANGUAGE_ID => $context->getLanguageId(),
            HttpCacheCookieEvent::TAX_STATE => $context->getTaxState(),
            HttpCacheCookieEvent::LOGGED_IN_STATE => $context->getCustomer() ? 'logged-in' : 'not-logged-in',
        ];

        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                $parts[$cookie] = $request->cookies->get($cookie);
            }
        }

        $event = new HttpCacheCookieEvent($request, $context, $parts);
        $this->dispatcher->dispatch($event);

        return $event->getHash();
    }

    private function isCacheHashRequired(Request $request, SalesChannelContext $salesChannelContext, Cart $cart): bool
    {
        if ($salesChannelContext->getCustomer() !== null) {
            // cache hash is required for logged in customers
            return true;
        }

        if ($cart->getLineItems()->count() > 0) {
            // cache hash is required for filled carts
            return true;
        }

        if ($salesChannelContext->getCurrencyId() !== $salesChannelContext->getSalesChannel()->getCurrencyId()) {
            // cache hash is required for non-default currency
            return true;
        }

        // check if cache relevant cookies are set
        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                return true;
            }
        }

        return false;
    }
}
