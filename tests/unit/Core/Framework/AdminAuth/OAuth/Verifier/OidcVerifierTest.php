<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Verifier;

use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\OidcVerifier;
use Shopware\Core\Framework\AdminAuth\Oidc\IdTokenValidator;
use Shopware\Core\Framework\AdminAuth\Oidc\OAuthIdentityMatcher;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleSynchronizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(OidcVerifier::class)]
class OidcVerifierTest extends TestCase
{
    private const PROVIDER_ID = 'a5b4885a89694a4c8e28e00b48b09dcc';
    private const CALLBACK_URL = 'http://localhost/api/_action/admin-auth/oidc/' . self::PROVIDER_ID . '/callback';

    public function testSupportsOnlyTheOidcMethod(): void
    {
        $verifier = $this->createVerifier();

        static::assertTrue($verifier->supports('oidc'));
        static::assertFalse($verifier->supports('password'));
        static::assertFalse($verifier->supports('webauthn'));
    }

    public function testVerifyPrimaryRejectsMissingParameters(): void
    {
        $verifier = $this->createVerifier();

        try {
            $verifier->verifyPrimary(['providerId' => self::PROVIDER_ID, 'code' => 'auth-code']);
            static::fail('an incomplete payload must be rejected');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
            static::assertSame('Missing OIDC provider, code or nonce.', $exception->getHint());
        }
    }

    public function testVerifyPrimaryRejectsAnUnknownProvider(): void
    {
        $verifier = $this->createVerifier(provider: null);

        try {
            $verifier->verifyPrimary($this->payload());
            static::fail('an unknown provider must be rejected');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
            static::assertSame('Unknown or inactive OIDC provider.', $exception->getHint());
        }
    }

    public function testVerifyPrimaryRejectsAnInactiveProvider(): void
    {
        $verifier = $this->createVerifier(provider: $this->provider(active: false));

        try {
            $verifier->verifyPrimary($this->payload());
            static::fail('an inactive provider must be rejected');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
            static::assertSame('Unknown or inactive OIDC provider.', $exception->getHint());
        }
    }

    public function testVerifyPrimaryExchangesTheCodeResolvesTheUserAndSyncsRoles(): void
    {
        $provider = $this->provider();
        $claims = $this->claims();
        $userId = Uuid::randomHex();

        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->with($provider, 'auth-code', self::CALLBACK_URL)
            ->willReturn('raw-id-token');

        $idTokenValidator = $this->createMock(IdTokenValidator::class);
        $idTokenValidator->expects($this->once())
            ->method('validate')
            ->with($provider, 'raw-id-token', 'nonce-1')
            ->willReturn($claims);

        $identityMatcher = $this->createMock(OAuthIdentityMatcher::class);
        $identityMatcher->expects($this->once())
            ->method('resolve')
            ->willReturn($userId);

        $roleSynchronizer = $this->createMock(SsoRoleSynchronizer::class);
        $roleSynchronizer->expects($this->once())
            ->method('sync')
            ->with($userId, $provider, $claims);

        $verifier = $this->createVerifier($provider, $oidcClient, $idTokenValidator, $identityMatcher, $roleSynchronizer);

        static::assertSame($userId, $verifier->verifyPrimary($this->payload()));
    }

    public function testVerifyPrimaryWrapsDomainFailuresAsAccessDenied(): void
    {
        $idTokenValidator = $this->createMock(IdTokenValidator::class);
        $idTokenValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new \RuntimeException('nonce mismatch'));

        $roleSynchronizer = $this->createMock(SsoRoleSynchronizer::class);
        $roleSynchronizer->expects($this->never())->method('sync');

        $verifier = $this->createVerifier(
            idTokenValidator: $idTokenValidator,
            roleSynchronizer: $roleSynchronizer
        );

        try {
            $verifier->verifyPrimary($this->payload());
            static::fail('a failed id_token validation must be rejected');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('nonce mismatch', $exception->getHint());
        }
    }

    /**
     * @param AdminAuthProvider|false|null $provider `null` = registry without providers; `false` (default) = an active provider
     */
    private function createVerifier(
        AdminAuthProvider|false|null $provider = false,
        ?OidcClient $oidcClient = null,
        ?IdTokenValidator $idTokenValidator = null,
        ?OAuthIdentityMatcher $identityMatcher = null,
        ?SsoRoleSynchronizer $roleSynchronizer = null,
    ): OidcVerifier {
        if ($provider === false) {
            $provider = $this->provider();
        }

        $registry = static::createStub(ProviderRegistry::class);
        $registry->method('byId')->willReturn($provider);

        if ($oidcClient === null) {
            $oidcClient = static::createStub(OidcClient::class);
            $oidcClient->method('exchangeCode')->willReturn('raw-id-token');
        }

        if ($idTokenValidator === null) {
            $idTokenValidator = static::createStub(IdTokenValidator::class);
            $idTokenValidator->method('validate')->willReturn($this->claims());
        }

        if ($identityMatcher === null) {
            $identityMatcher = static::createStub(OAuthIdentityMatcher::class);
            $identityMatcher->method('resolve')->willReturn(Uuid::randomHex());
        }

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn(self::CALLBACK_URL);

        return new OidcVerifier(
            $registry,
            $oidcClient,
            $idTokenValidator,
            $identityMatcher,
            $roleSynchronizer ?? static::createStub(SsoRoleSynchronizer::class),
            $router
        );
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'providerId' => self::PROVIDER_ID,
            'code' => 'auth-code',
            'nonce' => 'nonce-1',
        ];
    }

    private function provider(bool $active = true): AdminAuthProvider
    {
        return new AdminAuthProvider(
            id: self::PROVIDER_ID,
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            active: $active,
        );
    }

    private function claims(): OidcClaims
    {
        return new OidcClaims(
            sub: 'idp-sub-1',
            email: 'jane@corp.example',
            emailVerified: true,
            name: 'Jane Doe',
            preferredUsername: 'jane',
        );
    }
}
