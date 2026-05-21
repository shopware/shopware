<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpAccessTokenEntity;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpAccessTokenRepository;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpAuthCodeRepository;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpClientRepository;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpRefreshTokenRepository;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpScopeRepository;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds a League OAuth2 AuthorizationServer instance configured for a
 * specific Sales Channel. The instance is short-lived (request-scoped) —
 * each HTTP request that touches the OAuth endpoints constructs a fresh
 * server bound to the request's Sales Channel context.
 *
 * Grants enabled:
 *   - authorization_code with PKCE (S256)
 *   - refresh_token
 *
 * Token lifetimes:
 *   - access:  1 hour
 *   - refresh: 30 days
 *   - auth code: 5 minutes
 *
 * Signing key: the Sales Channel's currently active UCP signing key (ES256
 * by default, ES384 also supported). Access tokens are JWS-signed with the
 * exact same key pipeline that signs RFC 9421 HTTP message signatures —
 * verifiers (incl. our own ResourceServer and any third-party resource
 * servers) discover the public key via the UCP profile's `jwks_uri`.
 *
 * League/OAuth2-server v8 ships with a hardcoded RSA signer in
 * {@see AccessTokenTrait::initJwtConfiguration()}. We override that in
 * {@see UcpAccessTokenEntity::initJwtConfiguration()} to use the matching
 * lcobucci/jwt `Ecdsa\Sha256` (or `Ecdsa\Sha384`) signer, so the EC private
 * key is signed/verified correctly. The corresponding `BearerTokenValidator`
 * for the ResourceServer is configured the same way.
 *
 * @internal
 */
#[Package('framework')]
class UcpAuthorizationServerFactory
{
    public function __construct(
        private readonly UcpClientRepository $clientRepository,
        private readonly UcpAccessTokenRepository $accessTokenRepository,
        private readonly UcpAuthCodeRepository $authCodeRepository,
        private readonly UcpRefreshTokenRepository $refreshTokenRepository,
        private readonly UcpScopeRepository $scopeRepository,
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
    }

    public function create(string $salesChannelId, Context $context): AuthorizationServer
    {
        $signingKey = $this->signingKeyProvider->getActive($salesChannelId, $context);
        if ($signingKey === null) {
            throw UcpException::keyGenerationFailed(
                'no active UCP signing key for sales channel ' . $salesChannelId
            );
        }
        $privatePem = $this->signingKeyProvider->getPrivateKeyPem($signingKey);

        // Rebind per-Sales-Channel repos
        $this->clientRepository->setSalesChannelId($salesChannelId);
        $this->accessTokenRepository->setSalesChannelId($salesChannelId);
        $this->authCodeRepository->setSalesChannelId($salesChannelId);

        // Propagate the EC algorithm (ES256 / ES384) to UcpAccessTokenEntity so
        // its overridden initJwtConfiguration() picks the matching ECDSA signer.
        UcpAccessTokenEntity::setSigningAlgorithm($signingKey->getAlgorithm());

        $privateKey = new CryptKey($privatePem, null, false);
        $encryptionKey = $this->resolveEncryptionKey();

        $server = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $privateKey,
            $encryptionKey
        );

        $authCodeGrant = new AuthCodeGrant(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new \DateInterval('PT5M')
        );
        // PKCE: UCP REQUIRES proof-key for all auth-code flows. League v8
        // ships PKCE-on-by-default; older v7 fall-back versions exposed an
        // `enable…` method. We invoke whichever is present so that the grant
        // ends up with PKCE-mandatory regardless of the underlying library
        // version Shopware ships with.
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (method_exists($authCodeGrant, 'enableCodeExchangeProofRequirementForPublicClients')) {
            $authCodeGrant->enableCodeExchangeProofRequirementForPublicClients();
        }
        $authCodeGrant->setRefreshTokenTTL(new \DateInterval('P30D'));
        $server->enableGrantType($authCodeGrant, new \DateInterval('PT1H'));

        $refreshGrant = new RefreshTokenGrant($this->refreshTokenRepository);
        $refreshGrant->setRefreshTokenTTL(new \DateInterval('P30D'));
        $server->enableGrantType($refreshGrant, new \DateInterval('PT1H'));

        return $server;
    }

    private function resolveEncryptionKey(): string
    {
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET');

        // League supports either a defuse Key or a base64 string ≥ 32 bytes.
        // This key only encrypts the opaque auth code envelope (not the JWT).
        // sha256 (not Hasher::hash) intentional: key derivation must be
        // cryptographically secure.
        // @phpstan-ignore-next-line shopware.hasher
        return base64_encode(substr(hash('sha256', $secret . 'ucp/oauth/v1', true), 0, 32));
    }
}
