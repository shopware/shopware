<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server\UcpResourceServerFactory;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Validates the `Authorization: Bearer <jwt>` header on UCP routes that
 * require user authentication. Sets request attributes for downstream
 * capability controllers:
 *
 *   _ucp_user_id    The Shopware customer id
 *   _ucp_scopes     The scope list granted to this token
 *   _ucp_client_id  The OAuth client (platform) the token was issued to
 *
 * Unauthenticated requests are NOT rejected here — the capability controllers
 * decide whether the scope is required or whether agent-only auth is enough.
 *
 * @internal
 */
#[Package('framework')]
class UcpAccessTokenAuthenticator
{
    public const ATTR_USER_ID = '_ucp_user_id';
    public const ATTR_SCOPES = '_ucp_scopes';
    public const ATTR_CLIENT_ID = '_ucp_client_id';

    public function __construct(
        private readonly UcpResourceServerFactory $resourceServerFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Validate the optional `Authorization: Bearer <jwt>` header.
     *
     *   - No header              → no-op (anonymous request, downstream code
     *                              decides whether anonymous access is OK).
     *   - Header + valid token   → set user/scope/client attributes.
     *   - Header + INVALID token → THROW. The client signalled intent to
     *                              authenticate; silently dropping the bearer
     *                              would let downstream code treat the
     *                              caller as anonymous which is worse than
     *                              an explicit 401 (and is the F5 bug).
     */
    public function authenticate(Request $request, string $salesChannelId, Context $context): void
    {
        $header = $request->headers->get('authorization');
        if (!\is_string($header) || stripos($header, 'bearer ') !== 0) {
            return;
        }

        $resourceServer = $this->resourceServerFactory->create($salesChannelId, $context, $request);
        $psrRequest = $this->toPsr($request);

        try {
            $validated = $resourceServer->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException $e) {
            $this->logger->info('UCP bearer token rejected', [
                'reason' => $e->getMessage(),
                'sales_channel_id' => $salesChannelId,
            ]);

            // Fail closed — RFC 6750 §3: the bearer-presenting client MUST be
            // told its token is invalid. We surface a UCP-scoped exception
            // (handled by UcpExceptionListener → 401) instead of letting the
            // request fall through as anonymous.
            throw UcpException::scopeRequired('(bearer-authenticated route)', 'invalid bearer token');
        }

        $request->attributes->set(self::ATTR_USER_ID, $validated->getAttribute('oauth_user_id'));
        $request->attributes->set(self::ATTR_SCOPES, (array) $validated->getAttribute('oauth_scopes', []));
        $request->attributes->set(self::ATTR_CLIENT_ID, $validated->getAttribute('oauth_client_id'));
    }

    private function toPsr(Request $request): ServerRequestInterface
    {
        $psr17 = new Psr17Factory();
        $bridge = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);

        return $bridge->createRequest($request);
    }
}
