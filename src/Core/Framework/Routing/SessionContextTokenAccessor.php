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
 * The sales channel context token held in the PHP session.
 *
 * Owner: a storefront request (SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST), creates the
 * session and mints the first token. Borrower: a Store API request declaring
 * `sw-context-source: session`, may only resume an existing session.
 *
 * With `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` the token lives under a
 * sales channel suffixed key; the plain key mirrors the channel currently browsed.
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
    public const CONTEXT_SOURCE_SESSION = 'session';

    /**
     * Set on borrower requests, keeps the response out of shared caches.
     */
    public const ATTRIBUTE_TOKEN_FROM_SESSION = 'sw-context-token-from-session';

    public const SESSION_ID_KEY = 'sessionId';

    private const BINDING_CONFIG_KEY = 'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel';

    private const ATTRIBUTE_SESSION_ID = 'sw-context-session-id';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     * @param bool $enabled kill switch for the borrower role only, see `shopware.routing.session_context_token.enabled`
     */
    public function __construct(
        array $sessionOptions,
        private readonly bool $enabled,
        private readonly SystemConfigService $systemConfigService
    ) {
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    public function isOwner(Request $request): bool
    {
        return (bool) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST);
    }

    public function isRequested(Request $request): bool
    {
        return $request->headers->get(PlatformRequest::HEADER_CONTEXT_SOURCE) === self::CONTEXT_SOURCE_SESSION;
    }

    public function isEligible(Request $request): bool
    {
        return $this->isRequested($request) && $this->ineligibilityReason($request) === null;
    }

    /**
     * Why a borrower may not use the session, null when it may. A session is only ever resumed, never
     * created. Shared-cacheable routes are allowed: requests bypass the built-in cache and their
     * responses are forced no-store.
     */
    public function ineligibilityReason(Request $request): ?string
    {
        if (!$this->enabled) {
            return 'session context resolution is disabled (see shopware.routing.session_context_token.enabled)';
        }

        if ($request->cookies->get($this->sessionName) === null) {
            return 'the request carries no storefront session cookie';
        }

        if (!$this->isSameSiteFetch($request)) {
            return 'the request is not a same-origin or same-site fetch';
        }

        return null;
    }

    public function startForOwner(Request $mainRequest, ?Request $currentRequest = null): void
    {
        if (!$this->isOwner($mainRequest)) {
            return;
        }

        /** @phpstan-ignore shopware.unsafeRequestHasSession (the owner deliberately starts the storefront session here) */
        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!$session->isStarted()) {
            $session->start();
            $session->set(self::SESSION_ID_KEY, $session->getId());
        }

        $salesChannelId = $this->salesChannelIdOf($mainRequest);

        // without a sales channel there is no token to keep, one is minted per request
        $token = $salesChannelId === null ? null : $this->readToken($session, $salesChannelId);

        if ($token === null) {
            $token = Random::getAlphanumericString(32);
            $this->writeToken($session, $salesChannelId, $token);
        }

        // under binding the plain key may still hold another channel's token
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        if ($currentRequest !== null && $currentRequest !== $mainRequest) {
            $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }
    }

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
     * Regenerates the session ID with every rotation and leaves the session open: Symfony's
     * AbstractSessionListener only emits the new session cookie for a session that is still started.
     *
     * @return bool whether the request is session sourced and the session was updated
     */
    public function rotate(Request $request, string $salesChannelId, string $token, bool $destroyOldSession = false): bool
    {
        $session = $this->sessionFor($request);

        if ($session === null) {
            return false;
        }

        // migrate() is a no-op on a closed session, and a borrower's was released after the read
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->migrate($destroyOldSession);
        $session->set(self::SESSION_ID_KEY, $session->getId());
        $request->attributes->set(self::ATTRIBUTE_SESSION_ID, $session->getId());
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
            return $request->hasSession(true) ? $request->getSession() : null;
        }

        return $this->resumeForBorrower($request);
    }

    private function resumeForBorrower(Request $request): ?SessionInterface
    {
        if (!$this->isEligible($request)) {
            return null;
        }

        /** @phpstan-ignore shopware.unsafeRequestHasSession (only reached with a session cookie, so an existing session is resumed and none created) */
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        // After a rotation, the incoming cookie still holds the old ID. Follow the ID established by
        // this request while still rejecting sessions minted by strict mode for an unknown cookie.
        $expectedId = $request->attributes->get(self::ATTRIBUTE_SESSION_ID, $request->cookies->get($this->sessionName));
        if ($session->getId() !== $expectedId) {
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
     * An absent header means a non-browser client, not a cross-site one.
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
     * The native save handler locks the session for as long as it is open.
     */
    private function release(SessionInterface $session): void
    {
        if ($session->isStarted()) {
            $session->save();
        }
    }
}
