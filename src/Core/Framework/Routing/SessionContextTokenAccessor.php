<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Reads and writes the sales channel context token that the storefront PHP session holds.
 *
 * This is what lets a same-origin single page application talk to the Store API with nothing but
 * `sw-access-key`, the storefront session cookie and a `sw-context-source: session` header: the
 * context token never has to be rendered into the page markup (leak surface) nor kept in sync by
 * the client (it goes stale as soon as a route rotates it).
 *
 * The `sw-context-source` header is a deliberate opt-in. Without it a token-less request keeps its
 * pre-existing meaning - a fresh throwaway context - so same-origin callers that rely on that
 * sandbox semantic (availability widgets, "what would shipping cost" probes) never capture the
 * shopper's real context by accident. The header also makes the intent visible to every layer in
 * front of PHP: a reverse proxy can bypass or partition its cache on a request header, which it
 * can never do on a cookie that the store-api cache strategy deliberately ignores.
 *
 * The session keys mirror \Shopware\Storefront\Framework\Routing\StorefrontSubscriber::startSession():
 * with `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` enabled the storefront keeps the
 * token under a sales-channel-suffixed key, and it always mirrors the current token into the plain key.
 * Reading therefore prefers the suffixed key and falls back to the plain one; writing updates the
 * suffixed key only when the storefront actually uses it, and always keeps the plain key in sync.
 *
 * Deliberately *not* implemented as a storefront listener bound to the Store API scope: the Store API
 * keeps its route scopes and its stateless request handling, only the credential lookup gains a
 * fallback. `sw-access-key` stays mandatory (see SalesChannelAuthenticationListener) and remains the
 * CSRF backstop, because a custom request header forces a CORS preflight for cross-origin callers.
 *
 * @internal
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
     * Marks a request whose context token was taken from - or written back to - the storefront
     * session. Such a response is shopper specific by definition and must not be shared-cached.
     */
    public const ATTRIBUTE_TOKEN_FROM_SESSION = 'sw-context-token-from-session';

    /**
     * The context token this request was resolved with, captured before the controller runs.
     *
     * The request header itself cannot be used for that: SalesChannelContextService::get() rewrites it
     * whenever a route builds a context for a different token, so by the time the response is on the
     * wire the header already carries the rotated value.
     */
    public const ATTRIBUTE_RESOLVED_TOKEN = 'sw-context-token-resolved';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     * @param bool $enabled prototype kill switch, see `shopware.routing.session_context_token.enabled`.
     *                      With it off the Store API never looks at the storefront session and mints
     *                      a fresh token instead, exactly as before.
     *
     * @internal
     */
    public function __construct(
        array $sessionOptions,
        private readonly bool $enabled
    ) {
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    /**
     * Whether this request may consult the storefront session at all.
     *
     * Requires the explicit `sw-context-source: session` opt-in, a cookie for the configured
     * session name - so that an existing session is resumed and none is ever created - and, when
     * the browser tells us, a same-origin or same-site fetch.
     *
     * Routes flagged for shared HTTP caching are excluded entirely: the store-api cache strategy
     * keys cache entries on headers and deliberately ignores cookies, so a session resolved
     * response on such a route would be indistinguishable from the anonymous cached variant - the
     * shopper would get their own context on a cache miss and the anonymous one on a cache hit.
     * The routes this feature exists for (cart, checkout, account) are never shared-cacheable.
     */
    public function isEligible(Request $request): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($request->headers->get(PlatformRequest::HEADER_CONTEXT_SOURCE) !== self::CONTEXT_SOURCE_SESSION) {
            return false;
        }

        if ($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_HTTP_CACHE)) {
            return false;
        }

        if ($request->cookies->get($this->sessionName) === null) {
            return false;
        }

        return $this->isSameSiteFetch($request);
    }

    /**
     * The context token the storefront session currently holds, or null when there is none.
     */
    public function read(Request $request, string $salesChannelId): ?string
    {
        $session = $this->resumeSession($request);

        if ($session === null) {
            return null;
        }

        try {
            foreach ($this->tokenKeys($salesChannelId) as $key) {
                $token = $session->get($key);

                if (\is_string($token) && $token !== '') {
                    return $token;
                }
            }

            return null;
        } finally {
            $this->release($session);
        }
    }

    /**
     * Follows a token rotation (guest registration, login, logout) into the storefront session, so
     * that the next storefront request does not resurrect the pre-rotation context.
     *
     * Every rotation that reaches this point is a privilege boundary, so the session ID is
     * regenerated the same way the storefront does it on login (StorefrontSubscriber::updateSession):
     * a session cookie planted before the rotation must not stay attached to the rotated context.
     *
     * The session is deliberately left open afterwards. Symfony's AbstractSessionListener only
     * saves the session and attaches the regenerated session cookie to the response for a session
     * that is still started when it runs - callers of this method must therefore run earlier in
     * the response chain than that listener.
     */
    public function write(Request $request, string $salesChannelId, string $token): void
    {
        $session = $this->resumeSession($request);

        if ($session === null) {
            return;
        }

        $session->migrate();
        $session->set('sessionId', $session->getId());

        $suffixedKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;

        if ($salesChannelId !== '' && $session->has($suffixedKey)) {
            $session->set($suffixedKey, $token);
        }

        // The storefront always mirrors the current token into the plain key, so code that only
        // knows that key (anonymous visitors) keeps working.
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    /**
     * @return list<string>
     */
    private function tokenKeys(string $salesChannelId): array
    {
        $keys = [];

        if ($salesChannelId !== '') {
            $keys[] = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
        }

        $keys[] = PlatformRequest::HEADER_CONTEXT_TOKEN;

        return $keys;
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

    private function resumeSession(Request $request): ?SessionInterface
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

    /**
     * Closes the session right after reading or writing it. The native save handler holds a lock for
     * as long as the session is open, so keeping it open would serialize the parallel Store API calls
     * a single page application fires during boot.
     */
    private function release(SessionInterface $session): void
    {
        if ($session->isStarted()) {
            $session->save();
        }
    }
}
