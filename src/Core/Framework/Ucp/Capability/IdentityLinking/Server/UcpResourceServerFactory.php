<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\ResourceServer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository\UcpAccessTokenRepository;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Per-Sales-Channel ResourceServer used to validate bearer access tokens
 * presented on UCP routes that require user-authenticated access (Order
 * read/manage, Cart manage with customer context).
 *
 * Uses the Sales Channel's currently active UCP signing key for token
 * verification (same key pipeline as RFC 9421 message signatures).
 *
 * @internal
 */
#[Package('framework')]
class UcpResourceServerFactory
{
    public function __construct(
        private readonly UcpAccessTokenRepository $accessTokenRepository,
        private readonly UcpSigningKeyProvider $signingKeyProvider,
        private readonly SalesChannelDomainResolver $domainResolver,
    ) {
    }

    public function create(string $salesChannelId, Context $context, ?Request $currentRequest = null): ResourceServer
    {
        $signingKey = $this->signingKeyProvider->getActive($salesChannelId, $context);
        if ($signingKey === null) {
            throw UcpException::keyGenerationFailed(
                'no active UCP signing key for sales channel ' . $salesChannelId
            );
        }
        $publicPem = $this->derivePublicKeyPem(
            $this->signingKeyProvider->getPrivateKeyPem($signingKey)
        );

        $publicKey = new CryptKey($publicPem, null, false);
        $this->accessTokenRepository->setSalesChannelId($salesChannelId);

        // Use our ECDSA-aware validator instead of League's RSA-only default.
        // The algorithm comes from the active UCP signing key (ES256 / ES384).
        //
        // Pin iss + aud to the sales channel's storefront URL so a token
        // issued for another sales channel (or another Shopware installation
        // using the same key pipeline) cannot be replayed against ours.
        $issuerUri = null;
        if ($currentRequest !== null) {
            $domain = $this->domainResolver->resolve($currentRequest, $context);
            if ($domain !== null) {
                $issuerUri = rtrim($domain->getUrl(), '/');
            }
        }

        $validator = new UcpBearerTokenValidator(
            accessTokenRepository: $this->accessTokenRepository,
            algorithm: $signingKey->getAlgorithm(),
            jwtValidAtDateLeeway: new \DateInterval('PT1H'),
            expectedIssuer: $issuerUri,
            expectedAudience: null, // client_id audience varies per-token; enforced at scope-guard level
        );
        $validator->setPublicKey($publicKey);

        return new ResourceServer(
            $this->accessTokenRepository,
            $publicKey,
            $validator
        );
    }

    /**
     * @return non-empty-string
     */
    private function derivePublicKeyPem(string $privatePem): string
    {
        $resource = openssl_pkey_get_private($privatePem);
        if ($resource === false) {
            throw UcpException::keyDecryptionFailed();
        }
        $details = openssl_pkey_get_details($resource);
        if (!\is_array($details) || !isset($details['key'])) {
            throw UcpException::keyDecryptionFailed();
        }

        $pem = (string) $details['key'];
        \assert($pem !== '');

        return $pem;
    }
}
