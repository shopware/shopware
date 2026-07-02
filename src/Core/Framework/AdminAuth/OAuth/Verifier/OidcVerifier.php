<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Verifier;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\AdminAuth\Oidc\IdTokenValidator;
use Shopware\Core\Framework\AdminAuth\Oidc\OAuthIdentityMatcher;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleSynchronizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Primary verifier for external OIDC providers.
 *
 * The controller callback validates the CSRF state, then drives the shared authorization server
 * with `method=oidc`, the authorization `code`, the resolved `providerId` and the per-login
 * `nonce`. This verifier exchanges the code, validates the id_token and resolves (or provisions)
 * the user.
 *
 * @internal
 */
#[Package('framework')]
class OidcVerifier implements PrimaryVerifierInterface
{
    final public const METHOD = 'oidc';

    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly OidcClient $oidcClient,
        private readonly IdTokenValidator $idTokenValidator,
        private readonly OAuthIdentityMatcher $identityMatcher,
        private readonly SsoRoleSynchronizer $roleSynchronizer,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(string $method): bool
    {
        return $method === self::METHOD;
    }

    public function verifyPrimary(array $payload): string
    {
        $providerId = $payload['providerId'] ?? null;
        $code = $payload['code'] ?? null;
        $nonce = $payload['nonce'] ?? null;

        if (!\is_string($providerId) || !\is_string($code) || !\is_string($nonce)) {
            throw OAuthServerException::invalidRequest('code', 'Missing OIDC provider, code or nonce.');
        }

        $provider = $this->providerRegistry->byId($providerId);

        if ($provider === null || !$provider->active) {
            throw OAuthServerException::invalidRequest('providerId', 'Unknown or inactive OIDC provider.');
        }

        $redirectUri = $this->router->generate(
            'api.admin_auth.oidc.callback',
            ['providerId' => $provider->id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $idToken = $this->oidcClient->exchangeCode($provider, $code, $redirectUri);
            $claims = $this->idTokenValidator->validate($provider, $idToken, $nonce);

            $userId = $this->identityMatcher->resolve($provider, $claims, Context::createDefaultContext());

            // No ADMIN_AUTH flag check needed: this verifier is only reachable through the
            // admin_primary grant, which is registered only while the flag is active.
            $this->roleSynchronizer->sync($userId, $provider, $claims);

            return $userId;
        } catch (\Throwable $exception) {
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
        }
    }
}
