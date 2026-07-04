<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HmacSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RsaSha256;
use Lcobucci\JWT\Token\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Oidc\IdTokenValidator;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcDiscoveryService;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(IdTokenValidator::class)]
class IdTokenValidatorTest extends TestCase
{
    private const CLIENT_ID = 'the-client-id';
    private const ISSUER = 'https://idp.example.com';
    private const NONCE = 'per-login-nonce';
    private const KEY_ID = 'test-key-1';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-07-02 12:00:00');
    }

    public function testValidatesATokenAndMapsTheClaims(): void
    {
        $token = $this->buildToken(extraClaims: [
            'email' => 'jane@example.com',
            'email_verified' => true,
            'name' => 'Jane Doe',
            'preferred_username' => 'jane',
            'groups' => ['idp-admins'],
        ]);

        $claims = $this->createValidator()->validate($this->provider(), $token, self::NONCE);

        static::assertSame('user-123', $claims->sub);
        static::assertSame('jane@example.com', $claims->email);
        static::assertTrue($claims->emailVerified);
        static::assertSame('Jane Doe', $claims->name);
        static::assertSame('jane', $claims->preferredUsername);
        static::assertSame(['idp-admins'], $claims->getClaim('groups'));
    }

    public function testRejectsAGarbageToken(): void
    {
        $this->expectExceptionObject(AdminAuthException::invalidIdToken('the token could not be parsed'));

        $this->createValidator()->validate($this->provider(), 'not-a-jwt', self::NONCE);
    }

    public function testRejectsAnExpiredToken(): void
    {
        $token = $this->buildToken(expiresAt: $this->clock->now()->modify('-1 minute'));

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('the token is expired'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    public function testRejectsAWrongAudience(): void
    {
        $token = $this->buildToken(audience: 'other-client');

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('the audience does not match the configured client id'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    public function testRejectsAWrongIssuer(): void
    {
        $token = $this->buildToken(issuer: 'https://evil.example.com');

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('issuer mismatch'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    public function testRejectsAWrongNonce(): void
    {
        $token = $this->buildToken(nonce: 'replayed-nonce');

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('nonce mismatch'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    public function testRejectsATamperedPayload(): void
    {
        $parts = explode('.', $this->buildToken());

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        $payload['sub'] = 'someone-else';
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('the signature could not be verified against the provider JWKS'));

        $this->createValidator()->validate($this->provider(), implode('.', $parts), self::NONCE);
    }

    public function testRejectsATokenSignedWithAnUnknownKey(): void
    {
        $token = $this->buildToken(kid: 'unknown-key');

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('the signature could not be verified against the provider JWKS'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    public function testRejectsAnUnsupportedAlgorithm(): void
    {
        $token = Builder::new(new JoseEncoder(), ChainedFormatter::default())
            ->relatedTo('user-123')
            ->issuedBy(self::ISSUER)
            ->permittedFor(self::CLIENT_ID)
            ->expiresAt($this->clock->now()->modify('+1 hour'))
            ->withClaim('nonce', self::NONCE)
            ->getToken(new HmacSha256(), InMemory::plainText('a-shared-secret-of-sufficient-length'))
            ->toString();

        $this->expectExceptionObject(AdminAuthException::invalidIdToken('unsupported signature algorithm "HS256"'));

        $this->createValidator()->validate($this->provider(), $token, self::NONCE);
    }

    private function createValidator(): IdTokenValidator
    {
        $jwks = json_decode((string) file_get_contents(__DIR__ . '/_fixtures/jwks.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($jwks);

        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->method('getJwks')->willReturn($jwks['keys']);

        $discovery = $this->createMock(OidcDiscoveryService::class);
        $discovery->method('resolveEndpoints')->willReturn([
            'issuer' => self::ISSUER,
            'authorizationEndpoint' => self::ISSUER . '/authorize',
            'tokenEndpoint' => self::ISSUER . '/token',
            'jwksUri' => self::ISSUER . '/jwks',
        ]);

        return new IdTokenValidator($oidcClient, $discovery, $this->clock);
    }

    /**
     * @param non-empty-string $audience
     * @param non-empty-string $issuer
     * @param non-empty-string $nonce
     * @param non-empty-string $kid
     * @param array<non-empty-string, mixed> $extraClaims
     */
    private function buildToken(
        ?\DateTimeImmutable $expiresAt = null,
        string $audience = self::CLIENT_ID,
        string $issuer = self::ISSUER,
        string $nonce = self::NONCE,
        string $kid = self::KEY_ID,
        array $extraClaims = [],
    ): string {
        $builder = Builder::new(new JoseEncoder(), ChainedFormatter::default())
            ->withHeader('kid', $kid)
            ->relatedTo('user-123')
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->expiresAt($expiresAt ?? $this->clock->now()->modify('+1 hour'))
            ->withClaim('nonce', $nonce);

        foreach ($extraClaims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $privateKey = (string) file_get_contents(__DIR__ . '/_fixtures/rsa_private.pem');
        \assert($privateKey !== '');

        return $builder->getToken(new RsaSha256(), InMemory::plainText($privateKey))->toString();
    }

    private function provider(): AdminAuthProvider
    {
        $id = Uuid::randomHex();

        return new AdminAuthProvider(
            id: $id,
            providerKey: $id,
            label: 'Test IdP',
            clientId: self::CLIENT_ID,
            clientSecret: 'secret',
            issuer: self::ISSUER,
        );
    }
}
