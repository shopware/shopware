<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The sales channel context token as it lives in the PHP session - one implementation for both
 * surfaces that use it.
 *
 * Two roles:
 *
 * - The *owner* is the storefront request, recognizable by SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST,
 *   which the storefront request transformer sets before routing and never for a registered API
 *   prefix such as /store-api. The owner creates the session, mints the first token, keeps the
 *   session open for the whole request and is subject to none of the borrower conditions.
 * - The *borrower* is a Store API request declaring `sw-context-source: session`. It may only resume
 *   an existing session, under the conditions of ineligibilityReason(), and releases the session
 *   right after reading so the parallel calls a single page application fires during boot do not
 *   serialize on the session lock. This is what lets a same-origin SPA talk to the Store API with
 *   nothing but `sw-access-key`, the session cookie and that header: the context token never has to
 *   be rendered into the page markup (leak surface) nor kept in sync by the client (it goes stale as
 *   soon as a route rotates it).
 *
 * The `sw-context-source` header is a deliberate opt-in. Without it a token-less request keeps its
 * pre-existing meaning - a fresh throwaway context - so same-origin callers that rely on that
 * sandbox semantic never capture the shopper's real context by accident, and the intent is visible
 * to every layer in front of PHP as a header, which a reverse proxy can act on where it can never
 * act on a cookie the store-api cache strategy deliberately ignores. Declaring it is a contract:
 * when the session cannot be used the request fails (see SalesChannelRequestContextResolver).
 *
 * Key layout: the token lives under `sw-context-token`. With
 * `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` enabled every sales channel keeps
 * its own token under a sales-channel-suffixed key, and the plain key mirrors the token of the
 * channel currently browsed for code that only knows the plain key. A session created before that
 * flag was switched on holds no token for the channel until the next storefront page view mints one;
 * a borrower gets the contract error until then rather than a plain-key fallback, which would hand
 * it a token the storefront is about to orphan.
 *
 * `sw-access-key` stays mandatory for borrowers (see SalesChannelAuthenticationListener) and remains
 * the CSRF backstop, because a custom request header forces a CORS preflight for cross-origin
 * callers - `sw-context-source` is deliberately absent from the CORS allow-list as well.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Routing\SessionContextTokenResolutionTest
 */
#[Package('framework')]
class SessionContextTokenAccessor
{
    /**
     * The `sw-context-source` header value with which a client declares that its context lives in
     * the storefront session. Any other value (or no header at all) leaves the request with the
     * classic token semantics.
     */
    public const CONTEXT_SOURCE_SESSION = 'session';

    /**
     * Marks a borrower request whose context token was taken from - or written back to - the
     * session. Such a response is shopper specific by definition and must not be shared-cached.
     */
    public const ATTRIBUTE_TOKEN_FROM_SESSION = 'sw-context-token-from-session';

    /**
     * Bookkeeping key holding the current session ID, stamped when the session is started and again
     * after every rotation.
     */
    public const SESSION_ID_KEY = 'sessionId';

    private const BINDING_CONFIG_KEY = 'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     * @param bool $enabled kill switch for the borrower role only, see
     *                      `shopware.routing.session_context_token.enabled`. The storefront's own
     *                      session handling is unaffected by it.
     */
    public function __construct(
        array $sessionOptions,
        private readonly bool $enabled,
        private readonly SystemConfigService $systemConfigService
    ) {
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    /**
     * Whether this is the surface the shopper is browsing, i.e. the request that owns the session.
     */
    public function isOwner(Request $request): bool
    {
        return (bool) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);
    }

    /**
     * Whether the client declared that its context lives in the storefront session.
     */
    public function isRequested(Request $request): bool
    {
        return $request->headers->get(PlatformRequest::HEADER_CONTEXT_SOURCE) === self::CONTEXT_SOURCE_SESSION;
    }

    /**
     * Whether a borrower may consult the session: it declared the session as its context source,
     * and nothing stands in the way of honoring that.
     */
    public function isEligible(Request $request): bool
    {
        return $this->isRequested($request) && $this->ineligibilityReason($request) === null;
    }

    /**
     * Why an opted-in borrower may not consult the session, or null when it may.
     *
     * Public so the resolver can turn the reason into a client-facing error: declaring the session
     * as context source is a contract, and silently degrading to a fresh throwaway context would
     * surface as an inexplicably empty cart instead of a diagnosable response.
     *
     * The individual conditions: an existing session must be resumable - never created - so a
     * cookie for the configured session name is required; the fetch must be same-origin or
     * same-site when the browser tells us; and routes flagged for shared HTTP caching are excluded
     * entirely, because the store-api cache strategy keys cache entries on headers and deliberately
     * ignores cookies - a session resolved response on such a route would be indistinguishable from
     * the anonymous cached variant, giving the shopper their own context on a cache miss and the
     * anonymous one on a cache hit. The routes this exists for (cart, checkout, account) are never
     * shared-cacheable.
     */
    public function ineligibilityReason(Request $request): ?string
    {
        if (!$this->enabled) {
            return 'session context resolution is disabled (see shopware.routing.session_context_token.enabled)';
        }

        if ($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_HTTP_CACHE)) {
            return 'the route is shared-cacheable and must stay independent of the session cookie';
        }

        if ($request->cookies->get($this->sessionName) === null) {
            return 'the request carries no storefront session cookie';
        }

        if (!$this->isSameSiteFetch($request)) {
            return 'the request is not a same-origin or same-site fetch';
        }

        return null;
    }

    /**
     * Owner role: starts (or creates) the session, makes sure it holds a token for the sales channel
     * being browsed, and puts that token on the request headers - on the sub request too, so
     * fragments run with the same context as the page.
     */
    public function startForOwner(Request $mainRequest, ?Request $currentRequest = null): void
    {
        if (!$this->isOwner($mainRequest)) {
            return;
        }

        /**
         * @phpstan-ignore shopware.unsafeRequestHasSession (The owner is the one place that deliberately
         * starts the storefront session - and thereby takes the PHP session lock - for the request.)
         */
        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!$session->isStarted()) {
            $session->start();
            $session->set(self::SESSION_ID_KEY, $session->getId());
        }

        $salesChannelId = $this->salesChannelIdOf($mainRequest);

        // Without a sales channel there is nothing a token could belong to, so one is minted per request.
        $token = $salesChannelId === null ? null : $this->readToken($session, $salesChannelId);

        if ($token === null) {
            $token = Random::getAlphanumericString(32);
            $this->writeToken($session, $salesChannelId, $token);
        }

        // Under binding the plain key may still carry another channel's token; it always mirrors the
        // token of the channel being browsed.
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        if ($currentRequest !== null && $currentRequest !== $mainRequest) {
            $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }
    }

    /**
     * Borrower role: the token the session holds for the sales channel, or null when there is none.
     */
    public function read(Request $request, string $salesChannelId): ?string
    {
        $session = $this->resumeForBorrower($request);

        if ($session === null) {
            return null;
        }

        try {
            return $this->readToken($session, $this->normalize($salesChannelId));
        } finally {
            $this->release($session);
        }
    }

    /**
     * Follows a token rotation - login, guest registration, logout, password change, an expired
     * token swapped by the context service - into the session, so that the next request on either
     * surface does not resurrect the pre-rotation context.
     *
     * Every rotation is a privilege boundary, so the session ID is regenerated along with it: a
     * session cookie planted before the rotation must not stay attached to the rotated context.
     *
     * The session is left open afterwards. Symfony's AbstractSessionListener saves it and attaches
     * the regenerated session cookie to the response, but only for a session that is still started
     * when that listener runs.
     *
     * @return bool whether the session was updated, i.e. whether the request is session sourced at all
     */
    public function rotate(Request $request, string $salesChannelId, string $token, bool $destroyOldSession = false): bool
    {
        $session = $this->sessionFor($request);

        if ($session === null) {
            return false;
        }

        // migrate() is a silent no-op on a session that is not active - which a borrower's session
        // is not, it was released right after the read.
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->migrate($destroyOldSession);
        $session->set(self::SESSION_ID_KEY, $session->getId());
        $this->writeToken($session, $this->normalize($salesChannelId), $token);

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        if (!$this->isOwner($request)) {
            $request->attributes->set(self::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        }

        return true;
    }

    private function sessionFor(Request $request): ?SessionInterface
    {
        if ($this->isOwner($request)) {
            // The owner initialized its session at kernel.request; an uninitialized one means this
            // request never got that far.
            return $request->hasSession(true) ? $request->getSession() : null;
        }

        return $this->resumeForBorrower($request);
    }

    private function resumeForBorrower(Request $request): ?SessionInterface
    {
        if (!$this->isEligible($request)) {
            return null;
        }

        /**
         * @phpstan-ignore shopware.unsafeRequestHasSession (Store API requests only carry the lazy session
         * factory, so $skipIfUninitialized = true would never see it. The session cookie check in
         * isEligible() above is what keeps this safe: an existing session is resumed, never created.)
         */
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        // `session.use_strict_mode` (Symfony enforces it) makes PHP mint a fresh ID when the cookie
        // value does not belong to an existing session. Such a session is not the storefront session
        // the cookie promised, so no token may be read from or written to it.
        if ($session->getId() !== $request->cookies->get($this->sessionName)) {
            $this->release($session);

            return null;
        }

        return $session;
    }

    private function salesChannelIdOf(Request $request): ?string
    {
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        if ($salesChannelId === null) {
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

            if ($context instanceof SalesChannelContext) {
                $salesChannelId = $context->getSalesChannelId();
            }
        }

        return \is_string($salesChannelId) ? $this->normalize($salesChannelId) : null;
    }

    private function normalize(string $salesChannelId): ?string
    {
        return $salesChannelId !== '' ? $salesChannelId : null;
    }

    private function tokenKey(?string $salesChannelId): string
    {
        if ($salesChannelId !== null && $this->systemConfigService->getBool(self::BINDING_CONFIG_KEY)) {
            return PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
        }

        return PlatformRequest::HEADER_CONTEXT_TOKEN;
    }

    private function readToken(SessionInterface $session, ?string $salesChannelId): ?string
    {
        $token = $session->get($this->tokenKey($salesChannelId));

        return \is_string($token) && $token !== '' ? $token : null;
    }

    private function writeToken(SessionInterface $session, ?string $salesChannelId, string $token): void
    {
        $session->set($this->tokenKey($salesChannelId), $token);
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    /**
     * `Sec-Fetch-Site` is set by every browser that can reach this code path, so an absent header
     * means a non-browser client (server-to-server call, curl, test) rather than a cross-site one.
     * Those cannot carry another origin's cookies, so they are not the threat this guards against.
     */
    private function isSameSiteFetch(Request $request): bool
    {
        $fetchSite = $request->headers->get('Sec-Fetch-Site');

        if ($fetchSite === null || $fetchSite === '') {
            return true;
        }

        return \in_array(strtolower($fetchSite), ['same-origin', 'same-site'], true);
    }

    /**
     * Closes the session right after a borrower read. The native save handler holds a lock for as
     * long as the session is open, so keeping it open would serialize the parallel Store API calls a
     * single page application fires during boot.
     */
    private function release(SessionInterface $session): void
    {
        if ($session->isStarted()) {
            $session->save();
        }
    }
}
