<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Rest;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge\UcpAccessTokenAuthenticator;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\Framework\Ucp\Idempotency\IdempotencyStore;
use Shopware\Core\Framework\Ucp\Negotiation\NegotiationOrchestrator;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileFetcher;
use Shopware\Core\Framework\Ucp\Profile\UrlSafetyValidator;
use Shopware\Core\Framework\Ucp\Transport\Embedded\EmbeddedSessionFactory;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureVerifier;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Pre-controller event subscriber that:
 *
 *   1. Detects requests targeting UCP-scoped routes
 *   2. Resolves the Sales Channel via Host header
 *   3. Loads the UCP config and rejects if inactive
 *   4. Parses the UCP-Agent header to obtain the platform profile URI
 *   5. Runs capability negotiation against the cached / freshly fetched platform profile
 *   6. Builds a UcpRequestContext and attaches it to the Request
 *
 * @internal
 */
#[Package('framework')]
class UcpAgentRequestResolver implements EventSubscriberInterface
{
    private const ATTR_IDEMPOTENCY_REPLAY = '_ucp_idempotency_replay';

    /**
     * Routes that mutate state and therefore SHOULD demand Idempotency-Key.
     */
    private const NON_IDEMPOTENT_ROUTES = [
        'ucp.cart.create',
        'ucp.cart.update',
        'ucp.cart.cancel',
        'ucp.cart.discount.apply',
        'ucp.checkout.create',
        'ucp.checkout.update',
        'ucp.checkout.complete',
        'ucp.checkout.cancel',
    ];

    public function __construct(
        private readonly SalesChannelDomainResolver $domainResolver,
        private readonly UcpConfigProvider $configProvider,
        private readonly NegotiationOrchestrator $negotiationOrchestrator,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly Connection $connection,
        private readonly Rfc9421SignatureVerifier $signatureVerifier,
        private readonly PlatformProfileFetcher $platformProfileFetcher,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly EmbeddedSessionFactory $embeddedSessionFactory,
        private readonly UrlSafetyValidator $urlSafetyValidator,
        private readonly LoggerInterface $logger,
        private readonly ?UcpAccessTokenAuthenticator $accessTokenAuthenticator = null,
        private readonly string $environment = 'prod',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 9000],
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!$this->isUcpRoute($request)) {
            return;
        }
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            throw UcpException::salesChannelNotConfigured('(no domain match)');
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive()) {
            throw UcpException::salesChannelNotConfigured($domain->getSalesChannelId());
        }

        $agentHeaderValue = $request->headers->get(UcpAgentHeader::HEADER_NAME);

        // MCP transport: per UCP overview.md the agent profile MAY arrive in
        // the JSON-RPC envelope as `params._meta.ucp-agent.profile` instead of
        // the HTTP header. We mirror it onto the header so the rest of the
        // pipeline (signature verification, idempotency, logging) sees a
        // uniform request shape.
        if ($agentHeaderValue === null && str_starts_with($request->getPathInfo(), '/ucp/mcp')) {
            $agentHeaderValue = $this->extractAgentFromMcpBody($request);
            if ($agentHeaderValue !== null) {
                $request->headers->set(UcpAgentHeader::HEADER_NAME, $agentHeaderValue);
            }
        }

        if ($agentHeaderValue === null) {
            throw UcpException::invalidProfileUrl('(missing UCP-Agent header / _meta.ucp-agent)');
        }

        $agentHeader = UcpAgentHeader::parse($agentHeaderValue);

        $usesConformancePlaceholder = $this->isConformanceProfilePlaceholder($agentHeader);
        $usesConformanceSimulation = $this->isConformanceSimulationSignatureAccepted($request);
        $intersection = $usesConformancePlaceholder
            ? $this->negotiationOrchestrator->negotiateConformancePlaceholder(
                $config,
                $agentHeader->additionalParameters['version'] ?? $config->getUcpVersion()
            )
            : $this->negotiationOrchestrator->negotiate(
                $config,
                $agentHeader->profileUri,
                $context
            );
        if ($usesConformanceSimulation && !$usesConformancePlaceholder) {
            $intersection = $this->negotiationOrchestrator->negotiateConformancePlaceholder(
                $config,
                $intersection->protocolVersion
            );
        }

        if ($intersection->isEmpty()) {
            throw UcpException::capabilitiesIncompatible();
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? bin2hex(random_bytes(16)),
            $domain->getSalesChannelId(),
            [
                SalesChannelContextService::DOMAIN_ID => $domain->getId(),
                SalesChannelContextService::LANGUAGE_ID => $domain->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $domain->getCurrencyId(),
            ]
        );

        $ucpRequestContext = new UcpRequestContext(
            config: $config,
            salesChannelContext: $salesChannelContext,
            intersection: $intersection,
            platformProfileUri: $agentHeader->profileUri,
        );
        $request->attributes->set(UcpRequestContext::REQUEST_ATTRIBUTE, $ucpRequestContext);

        // RFC 9421 inbound signature verification — policy-driven per sales channel.
        $signatureVerified = $this->verifyInboundSignature($request, $config, $agentHeader->profileUri, $context);

        // Mark in request attributes so downstream code (notably SignalsExtractor)
        // can require strong auth before honouring platform-provided values.
        $request->attributes->set(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, $signatureVerified);

        // Embedded Protocol: when the bridge JS hits a UCP route the request
        // carries `X-UCP-Embedded-Session` — validate the opaque session token
        // against the row we issued at iframe-load time, and reject if it's
        // not bound to this cart on this sales channel.
        $this->verifyEmbeddedSession($request, $config->getSalesChannelId());

        // Idempotency-Key replay-cache lookup (raises 409 on body conflict, returns
        // cached response on match, no-op on miss).
        $this->checkIdempotencyReplay($event, $request, $config);

        $this->accessTokenAuthenticator?->authenticate($request, $config->getSalesChannelId(), $context);

        if (!$usesConformancePlaceholder && !$usesConformanceSimulation) {
            $this->persistNegotiationSession($config->getSalesChannelId(), $agentHeader->profileUri, $intersection, $config->getUcpVersion());
        }
    }

    /**
     * Commit the response onto the previously-claimed Idempotency-Key row so
     * subsequent retries with the same key return the same bytes.
     *
     * For 5xx we abort the claim instead of committing — the spec says retries
     * are expected to recover, so we don't want a frozen "failed" response.
     */
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $ucpContext = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$ucpContext instanceof UcpRequestContext) {
            return;
        }

        $idempotencyKey = $request->headers->get('idempotency-key');
        if (!\is_string($idempotencyKey) || $idempotencyKey === '') {
            return;
        }

        $routeName = (string) $request->attributes->get('_route');
        if (!\in_array($routeName, self::NON_IDEMPOTENT_ROUTES, true)) {
            return;
        }

        // Skip when claim() returned a replay — we did not enter the controller.
        if ($request->attributes->get(self::ATTR_IDEMPOTENCY_REPLAY, false) === true) {
            return;
        }

        $response = $event->getResponse();
        $salesChannelId = $ucpContext->config->getSalesChannelId();

        try {
            if ($response->getStatusCode() >= 500) {
                // Server error: free the claim so a fresh retry can run.
                $this->idempotencyStore->abort($salesChannelId, $idempotencyKey);

                return;
            }

            $this->idempotencyStore->commit($salesChannelId, $idempotencyKey, $response);
        } catch (\Throwable $e) {
            // Storage failure must not break the response — log and move on.
            $this->logger->warning('UCP idempotency commit failed', [
                'error' => $e->getMessage(),
                'key' => $idempotencyKey,
                'route' => $routeName,
            ]);
        }
    }

    /**
     * @return bool true when the request was cryptographically verified against
     *              the platform's published JWKS; false otherwise (policy off,
     *              policy log + failure, or no signature headers present).
     */
    private function verifyInboundSignature(
        Request $request,
        UcpSalesChannelConfigEntity $config,
        string $platformProfileUri,
        Context $context
    ): bool {
        if ($this->isConformanceSimulationSignatureAccepted($request)) {
            return true;
        }

        $policy = $config->getSignaturePolicy();
        if ($policy === UcpSalesChannelConfigEntity::SIGNATURE_POLICY_OFF) {
            return false;
        }

        // The platform's published JWKS lives in the same profile we used for
        // capability negotiation — re-use the cached document.
        try {
            $profile = $this->platformProfileFetcher->fetch($platformProfileUri, $context, $config->getPlatformAllowlist());
        } catch (\Throwable $e) {
            $this->logger->warning('UCP: platform profile fetch failed during signature verification', [
                'error' => $e->getMessage(),
            ]);
            if ($policy === UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT) {
                throw UcpException::signatureKeyNotFound('(profile unreachable)');
            }

            return false;
        }

        $jwks = $profile['signing_keys'] ?? [];
        if (!\is_array($jwks) || $jwks === []) {
            if ($policy === UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT) {
                throw UcpException::signatureKeyNotFound('(platform published no signing_keys)');
            }

            return false;
        }

        try {
            $this->signatureVerifier->verifyRequest($request, $jwks, $config->getSalesChannelId());

            return true;
        } catch (UcpException $e) {
            if ($policy === UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT) {
                throw $e;
            }
            $this->logger->warning('UCP: inbound signature failed (log-only mode)', [
                'error' => $e->getMessage(),
                'platform_profile_uri' => $platformProfileUri,
            ]);

            return false;
        }
    }

    /**
     * Validate the `X-UCP-Embedded-Session` header against the issuing row.
     * No-op when the header is absent (most agent traffic). Hard-rejects on
     * invalid/expired tokens — embedded clients MUST present a valid session.
     */
    private function verifyEmbeddedSession(Request $request, string $salesChannelId): void
    {
        $sessionToken = $request->headers->get('X-UCP-Embedded-Session');
        if (!\is_string($sessionToken) || $sessionToken === '') {
            return;
        }

        // The embedded bridge always carries the cart token in `sw-context-token`;
        // session is bound to (cart_id, sales_channel_id).
        $cartId = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (!\is_string($cartId) || $cartId === '') {
            throw UcpException::featureDisabled();
        }

        if (!$this->embeddedSessionFactory->verify($sessionToken, $cartId, $salesChannelId, $request->headers->get('origin'))) {
            throw UcpException::featureDisabled();
        }

        // Store a marker for downstream auditing (without leaking the token).
        $request->attributes->set(
            UcpRequestContext::ATTR_EMBEDDED_SESSION_ID,
            // sha256 (not Hasher::hash) intentional: audit marker derived from
            // the secret session token — must not leak preimage, so cryptographic
            // hashing is required.
            // @phpstan-ignore-next-line shopware.hasher
            'embedded:' . substr(hash('sha256', $sessionToken), 0, 16)
        );
    }

    /**
     * Pre-execution idempotency control:
     *   - Atomic claim with INSERT — concurrent retries can't both run.
     *   - Body fingerprint mismatch → 409.
     *   - Match with cached response → short-circuit replay.
     *   - In-flight (concurrent) → 409 to caller, they can poll later.
     */
    private function checkIdempotencyReplay(
        ControllerEvent $event,
        Request $request,
        UcpSalesChannelConfigEntity $config
    ): void {
        $routeName = (string) $request->attributes->get('_route');
        $idempotencyKey = $request->headers->get('idempotency-key');
        $isNonIdempotent = \in_array($routeName, self::NON_IDEMPOTENT_ROUTES, true);

        if (!$isNonIdempotent) {
            return;
        }

        if (!\is_string($idempotencyKey) || $idempotencyKey === '') {
            if ($config->isIdempotencyRequired()) {
                throw UcpException::idempotencyKeyRequired();
            }

            return;
        }

        $fingerprint = IdempotencyStore::computeFingerprint(
            $routeName,
            $request->getMethod(),
            $request->getPathInfo(),
            $request->getQueryString() ?? '',
            (string) $request->getContent()
        );

        $claim = $this->idempotencyStore->claim(
            $config->getSalesChannelId(),
            $idempotencyKey,
            $routeName,
            $fingerprint
        );

        if ($claim['status'] === IdempotencyStore::RESULT_FRESH) {
            return;
        }

        if ($claim['status'] === IdempotencyStore::RESULT_IN_FLIGHT) {
            // Another request with this key is still in flight. We surface
            // this as a conflict so the client can retry — preventing
            // double-execution while the first request completes.
            throw UcpException::idempotencyKeyConflict($idempotencyKey);
        }

        // RESULT_REPLAY: short-circuit with cached response.
        $cached = $claim['cached'];
        \assert(\is_array($cached));
        $response = new Response(
            $cached['body'],
            $cached['status']
        );
        foreach ($cached['headers'] as $name => $value) {
            $response->headers->set($name, $value);
        }
        $response->headers->set('Idempotency-Replay', '1');

        $request->attributes->set(self::ATTR_IDEMPOTENCY_REPLAY, true);
        $event->setController(static fn () => $response);
    }

    /**
     * Persists/updates the negotiation session so order webhooks know where to
     * send updates. Idempotent: a (sales_channel_id, platform_profile_hash)
     * unique key prevents duplicates.
     */
    private function persistNegotiationSession(
        string $salesChannelId,
        string $platformProfileUri,
        CapabilityIntersection $intersection,
        string $protocolVersion
    ): void {
        // sha256 (not Hasher::hash) intentional: the platform_profile_hash is
        // used to bind outbound order webhooks back to a specific platform —
        // we need cryptographic collision resistance, not just stability.
        // @phpstan-ignore-next-line shopware.hasher
        $hash = hash('sha256', $platformProfileUri);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        $existing = $this->connection->fetchOne(
            'SELECT id FROM ucp_negotiation_session WHERE sales_channel_id = ? AND platform_profile_hash = ? LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $hash]
        );

        if ($existing !== false) {
            $this->connection->executeStatement(
                'UPDATE ucp_negotiation_session SET active_capabilities = ?, protocol_version = ?, last_used_at = ?, updated_at = ? WHERE id = ?',
                [
                    json_encode($intersection->toArray(), \JSON_THROW_ON_ERROR),
                    $protocolVersion,
                    $now,
                    $now,
                    $existing,
                ]
            );

            return;
        }

        $this->connection->insert('ucp_negotiation_session', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'platform_profile_uri' => $platformProfileUri,
            'platform_profile_hash' => $hash,
            'active_capabilities' => json_encode($intersection->toArray(), \JSON_THROW_ON_ERROR),
            'protocol_version' => $protocolVersion,
            'last_used_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * Extract the UCP-Agent value from an inbound MCP JSON-RPC envelope.
     *
     * Looks at:
     *   - top-level `params._meta.ucp-agent.profile`
     *   - first-element if the body is a JSON-RPC batch array
     */
    private function extractAgentFromMcpBody(Request $request): ?string
    {
        $body = (string) $request->getContent();
        if ($body === '') {
            return null;
        }

        try {
            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $candidates = [];
        if (\is_array($payload)) {
            $candidates[] = $payload;
            if (array_is_list($payload)) {
                foreach ($payload as $item) {
                    if (\is_array($item)) {
                        $candidates[] = $item;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $params = $candidate['params'] ?? null;
            if (!\is_array($params)) {
                continue;
            }
            $meta = $params['_meta'] ?? null;
            if (!\is_array($meta)) {
                continue;
            }
            $ucpAgent = $meta['ucp-agent'] ?? null;
            if (!\is_array($ucpAgent)) {
                continue;
            }
            $profile = $ucpAgent['profile'] ?? null;
            if (!\is_string($profile) || $profile === '') {
                continue;
            }

            // SECURITY: defend against header-injection (CRLF) and SSRF
            // BEFORE wrapping the URL in a Structured-Field header. The
            // header pipeline trusts whatever we set, so we have to validate
            // here exactly like the HTTP header path would.
            if (preg_match('/[\r\n\x00-\x1F\x7F]/', $profile) === 1) {
                throw UcpException::invalidProfileUrl('(_meta.ucp-agent.profile contains control characters)');
            }
            if (!filter_var($profile, \FILTER_VALIDATE_URL)) {
                throw UcpException::invalidProfileUrl('(_meta.ucp-agent.profile is not a valid URL)');
            }
            try {
                // Run the same SSRF / cloud-metadata / private-IP / DNS checks
                // we would run when fetching the profile, so a bad URL is
                // rejected at ingress (not later during the fetch).
                $this->urlSafetyValidator->validateAndResolve($profile, null, $this->environment);
            } catch (UcpException $e) {
                throw $e;
            }

            return 'profile="' . str_replace('"', '\\"', $profile) . '"';
        }

        return null;
    }

    private function isConformanceSimulationSignatureAccepted(Request $request): bool
    {
        if (!$this->isConformanceMode()) {
            return false;
        }

        $signature = $request->headers->get('request-signature');
        if (!\is_string($signature) || $signature === '') {
            return false;
        }

        // The upstream Python suite currently sends `request-signature: test`.
        // Keep HMAC support as the real secret-bound variant for local runners
        // that want stronger simulation auth without RFC 9421.
        if (hash_equals('test', $signature)) {
            return true;
        }

        $secret = getenv('UCP_SIMULATION_SECRET') ?: ($_SERVER['UCP_SIMULATION_SECRET'] ?? $_ENV['UCP_SIMULATION_SECRET'] ?? '');
        if (!\is_string($secret) || $secret === '') {
            return false;
        }

        $body = (string) $request->getContent();
        $hmac = hash_hmac('sha256', strtoupper($request->getMethod()) . "\n" . $request->getPathInfo() . "\n" . $body, $secret);

        return hash_equals($hmac, $signature) || hash_equals('sha256=' . $hmac, $signature);
    }

    private function isConformanceProfilePlaceholder(UcpAgentHeader $agentHeader): bool
    {
        return $agentHeader->profileUri === '...' && $this->isConformanceMode();
    }

    private function isConformanceMode(): bool
    {
        if ($this->environment === 'prod') {
            return false;
        }

        return filter_var(getenv('UCP_CONFORMANCE_MODE') ?: ($_SERVER['UCP_CONFORMANCE_MODE'] ?? $_ENV['UCP_CONFORMANCE_MODE'] ?? false), \FILTER_VALIDATE_BOOL);
    }

    private function isUcpRoute(Request $request): bool
    {
        $path = $request->getPathInfo();

        // OAuth + well-known endpoints are storefront-scoped, not UCP-scoped — exclude
        if (str_starts_with($path, '/ucp/v1/.well-known/') || str_starts_with($path, '/ucp/v1/oauth/')) {
            return false;
        }
        if (str_starts_with($path, '/testing/')) {
            return false;
        }

        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (\is_array($routeScope) && \in_array(UcpRouteScope::ID, $routeScope, true)) {
            return true;
        }

        return str_starts_with($path, '/ucp/v1') || str_starts_with($path, '/ucp/mcp');
    }
}
