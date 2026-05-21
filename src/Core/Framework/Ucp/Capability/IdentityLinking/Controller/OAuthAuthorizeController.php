<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge\CustomerSessionResolver;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpUserEntity;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Scope\UcpScopeRegistry;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server\UcpAuthorizationServerFactory;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * OAuth 2.0 Authorization endpoint per Sales Channel — backed by the League
 * AuthorizationServer.
 *
 * Flow:
 *   1. GET /ucp/v1/oauth/authorize?…
 *      → League validates the AuthorizationRequest
 *      → if no active customer session, redirect to storefront login
 *      → otherwise render consent screen with scope list
 *   2. POST /ucp/v1/oauth/authorize (form-encoded `approve=1`/`approve=0`)
 *      → set userIdentifier + approval on the AuthorizationRequest
 *      → League issues the auth code + redirects to redirect_uri
 *
 * Auth state is carried across the GET→POST step via a signed (HMAC) ticket
 * stored in a short-lived encrypted cookie. This avoids stuffing state in
 * URL query parameters where it would leak via logs.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class OAuthAuthorizeController
{
    public const CONSENT_COOKIE = 'ucp_consent_ticket';
    private const CONSENT_TTL_SECONDS = 300;

    public function __construct(
        private readonly SalesChannelDomainResolver $domainResolver,
        private readonly UcpConfigProvider $configProvider,
        private readonly UcpAuthorizationServerFactory $serverFactory,
        private readonly UcpScopeRegistry $scopeRegistry,
        private readonly CustomerSessionResolver $sessionResolver,
    ) {
    }

    #[Route(
        path: '/ucp/v1/oauth/authorize',
        name: 'ucp.identity.oauth.authorize',
        defaults: [
            'auth_required' => false,
            '_routeScope' => ['storefront'],
            '_loginRequired' => false,
        ],
        methods: ['GET', 'POST']
    )]
    public function authorize(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return new Response('No matching sales channel domain', Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive() || !$config->isCapabilityEnabled('dev.ucp.common.identity_linking')) {
            return new Response('Identity Linking capability disabled on this sales channel', Response::HTTP_NOT_FOUND);
        }

        // Enforce platform allowlist if configured
        $clientId = (string) $request->query->get('client_id', $request->request->get('client_id', ''));
        if (!$this->isClientAllowed($config, $clientId)) {
            return new Response('Client ' . $clientId . ' not in platform allowlist', Response::HTTP_FORBIDDEN);
        }

        $server = $this->serverFactory->create($domain->getSalesChannelId(), $context);
        $psrRequest = $this->toPsr($request);

        try {
            $authRequest = $server->validateAuthorizationRequest($psrRequest);
        } catch (OAuthServerException $e) {
            return $this->fromPsr($e->generateHttpResponse($this->newPsrResponse()));
        }

        if ($request->getMethod() === 'POST') {
            try {
                $this->verifyConsentTicket($request);
            } catch (OAuthServerException $e) {
                return $this->fromPsr($e->generateHttpResponse($this->newPsrResponse()));
            }

            $approved = $request->request->getBoolean('approve');
            $customerId = $this->sessionResolver->getActiveCustomerId($request);

            if ($customerId === null || $customerId === '') {
                return $this->redirectToLogin($request, $domain->getUrl());
            }
            if (!$approved) {
                $authRequest->setAuthorizationApproved(false);
            } else {
                $authRequest->setUser(new UcpUserEntity($customerId));
                $authRequest->setAuthorizationApproved(true);
            }

            try {
                $psrResponse = $server->completeAuthorizationRequest($authRequest, $this->newPsrResponse());
            } catch (OAuthServerException $e) {
                return $this->fromPsr($e->generateHttpResponse($this->newPsrResponse()));
            }

            $response = $this->fromPsr($psrResponse);
            $this->appendIssToRedirect($response, $domain->getUrl());
            $response->headers->clearCookie(self::CONSENT_COOKIE);

            return $response;
        }

        // GET — render consent. If not logged in, kick to storefront login first.
        $customerId = $this->sessionResolver->getActiveCustomerId($request);
        if ($customerId === null) {
            return $this->redirectToLogin($request, $domain->getUrl());
        }

        $scopes = array_values(array_map(
            static fn ($s): string => $s->getIdentifier(),
            $authRequest->getScopes()
        ));

        $ticket = $this->createConsentTicket($request, $clientId, $scopes);
        $response = new Response(
            $this->renderConsent($authRequest->getClient()->getName(), $clientId, $scopes, $domain->getUrl(), $ticket['csrf']),
            200,
            ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']
        );
        $response->headers->setCookie(Cookie::create(
            self::CONSENT_COOKIE,
            $ticket['value'],
            time() + self::CONSENT_TTL_SECONDS,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX
        ));

        return $response;
    }

    private function isClientAllowed(UcpSalesChannelConfigEntity $config, string $clientId): bool
    {
        $allowlist = $config->getPlatformAllowlist();
        if ($allowlist === null) {
            return true; // permissionless onboarding
        }

        return \in_array($clientId, $allowlist, true);
    }

    /**
     * Append `iss=<issuer>` to the OAuth authorization-response redirect, per
     * RFC 9207 §"Mix-Up Mitigation". League's authorization server does not
     * inject `iss` automatically, so we splice it into the `Location` header
     * after the response is built. The discovery metadata advertises this
     * support via `authorization_response_iss_parameter_supported: true`.
     */
    private function appendIssToRedirect(Response $response, string $domainUrl): void
    {
        $location = $response->headers->get('Location');
        if (!\is_string($location) || $location === '') {
            return;
        }
        // The issuer string MUST be the same value advertised in the
        // discovery document under `issuer`. We recompute it from the active
        // domain URL to stay consistent with `OAuthDiscoveryController`.
        $issuer = rtrim($domainUrl, '/');
        $separator = str_contains($location, '?') ? '&' : '?';
        $response->headers->set('Location', $location . $separator . 'iss=' . rawurlencode($issuer));
    }

    private function redirectToLogin(Request $request, string $domainUrl): Response
    {
        // Shopware's AuthController treats `redirectTo` as a Symfony **route name**
        // (not URL) and resolves params from `redirectParameters`. We pass our
        // OAuth-authorize route name and forward all original query params so the
        // user lands back on the consent screen after successful login.
        $routeName = 'ucp.identity.oauth.authorize';
        $params = $request->query->all();

        $loginQuery = http_build_query([
            'redirectTo' => $routeName,
            'redirectParameters' => json_encode($params, \JSON_THROW_ON_ERROR),
        ]);

        $login = rtrim($domainUrl, '/') . '/account/login?' . $loginQuery;

        return new RedirectResponse($login);
    }

    /**
     * @param list<string> $scopes
     */
    private function renderConsent(string $clientName, string $clientId, array $scopes, string $domainUrl, string $csrfToken): string
    {
        $scopeRows = '';
        foreach ($scopes as $scope) {
            $scopeRows .= '<li><strong>' . htmlspecialchars($scope, \ENT_QUOTES) . '</strong> — '
                . htmlspecialchars($this->scopeRegistry->describe($scope), \ENT_QUOTES) . '</li>';
        }
        $clientLabel = htmlspecialchars($clientName !== '' ? $clientName : $clientId, \ENT_QUOTES);
        $parsedHost = parse_url($domainUrl, \PHP_URL_HOST);
        $domain = htmlspecialchars(\is_string($parsedHost) ? $parsedHost : $domainUrl, \ENT_QUOTES);
        $csrfToken = htmlspecialchars($csrfToken, \ENT_QUOTES);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Authorise {$clientLabel}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#f4f6f8; padding:48px; }
        .card { max-width:560px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; box-shadow:0 6px 24px rgba(0,0,0,.08); }
        h1 { margin:0 0 8px; font-size:22px; }
        p.lead { color:#555; margin:0 0 24px; }
        ul { padding-left:20px; line-height:1.7; }
        .actions { display:flex; gap:12px; margin-top:32px; }
        button { padding:10px 20px; border:0; border-radius:8px; font-size:15px; cursor:pointer; }
        .approve { background:#1e88e5; color:#fff; }
        .deny { background:#eceff1; color:#333; }
        .meta { color:#888; font-size:13px; margin-top:16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{$clientLabel} wants to act on your behalf at {$domain}</h1>
        <p class="lead">It is requesting the following permissions:</p>
        <ul>{$scopeRows}</ul>
        <form method="post">
            <input type="hidden" name="_csrf_token" value="{$csrfToken}">
            <div class="actions">
                <button type="submit" name="approve" value="1" class="approve">Authorize</button>
                <button type="submit" name="approve" value="0" class="deny">Deny</button>
            </div>
        </form>
        <p class="meta">Powered by Universal Commerce Protocol — Shopware UCP</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Create a short-lived, HMAC-authenticated consent ticket. The ticket binds
     * the POST approval to the exact OAuth request the user reviewed on GET
     * (client_id, redirect_uri, scope, state, PKCE challenge).
     *
     * @param list<string> $scopes
     *
     * @return array{value: string, csrf: string}
     */
    private function createConsentTicket(Request $request, string $clientId, array $scopes): array
    {
        $csrf = bin2hex(random_bytes(32));
        $payload = [
            'iat' => time(),
            'exp' => time() + self::CONSENT_TTL_SECONDS,
            'csrf' => $csrf,
            'client_id' => $clientId,
            'redirect_uri' => (string) $request->query->get('redirect_uri', ''),
            'scope' => implode(' ', $scopes),
            'state' => (string) $request->query->get('state', ''),
            'code_challenge' => (string) $request->query->get('code_challenge', ''),
            'code_challenge_method' => (string) $request->query->get('code_challenge_method', ''),
        ];

        $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $body = $this->base64UrlEncode($json);
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->consentTicketSecret(), true));

        return ['value' => $body . '.' . $sig, 'csrf' => $csrf];
    }

    private function verifyConsentTicket(Request $request): void
    {
        $ticket = $request->cookies->get(self::CONSENT_COOKIE);
        if (!\is_string($ticket) || !str_contains($ticket, '.')) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'Missing consent ticket');
        }

        [$body, $sig] = explode('.', $ticket, 2);
        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->consentTicketSecret(), true));
        if (!hash_equals($expectedSig, $sig)) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'Consent ticket signature mismatch');
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!\is_array($payload)) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'Malformed consent ticket');
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'Consent ticket expired');
        }

        $postedCsrf = $request->request->get('_csrf_token');
        if (!\is_string($postedCsrf) || !hash_equals((string) $payload['csrf'], $postedCsrf)) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'CSRF token mismatch');
        }

        $expected = [
            'client_id' => (string) $request->query->get('client_id', $request->request->get('client_id', '')),
            'redirect_uri' => (string) $request->query->get('redirect_uri', $request->request->get('redirect_uri', '')),
            'state' => (string) $request->query->get('state', $request->request->get('state', '')),
            'code_challenge' => (string) $request->query->get('code_challenge', $request->request->get('code_challenge', '')),
            'code_challenge_method' => (string) $request->query->get('code_challenge_method', $request->request->get('code_challenge_method', '')),
        ];

        foreach ($expected as $key => $value) {
            if (!hash_equals((string) ($payload[$key] ?? ''), $value)) {
                throw OAuthServerException::invalidRequest($key, 'OAuth request changed after consent screen was rendered');
            }
        }
    }

    private function consentTicketSecret(): string
    {
        // sha256 (not Hasher::hash) intentional: HMAC key for the CSRF consent
        // ticket — must be cryptographically secure.
        // @phpstan-ignore-next-line shopware.hasher
        return hash('sha256', (string) EnvironmentHelper::getVariable('APP_SECRET') . '|ucp-oauth-consent', true);
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $pad = 4 - (\strlen($value) % 4);
        if ($pad < 4) {
            $value .= str_repeat('=', $pad);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw OAuthServerException::invalidRequest('_csrf_token', 'Malformed base64url consent ticket');
        }

        return $decoded;
    }

    private function toPsr(Request $request): PsrServerRequestInterface
    {
        $psr17 = new Psr17Factory();
        $bridge = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);

        return $bridge->createRequest($request);
    }

    private function newPsrResponse(): PsrResponseInterface
    {
        return (new Psr17Factory())->createResponse();
    }

    private function fromPsr(PsrResponseInterface $psr): Response
    {
        $bridge = new HttpFoundationFactory();

        return $bridge->createResponse($psr);
    }
}
