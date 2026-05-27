<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\IdentityLinking\Auth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Auth\ClientAuthenticator;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileFetcher;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Negative-path coverage for the `private_key_jwt` (RFC 7523) branch of
 * the OAuth client authenticator. Each branch represents a distinct attack
 * surface — wrong algorithm, missing kid, iss/sub mismatch, expired token,
 * audience mismatch. Failing any of these silently means an attacker can
 * forge a client assertion for someone else's client_id.
 *
 * @internal
 */
#[CoversClass(ClientAuthenticator::class)]
class ClientAuthenticatorBranchesTest extends TestCase
{
    private const TOKEN_ENDPOINT = 'https://shop.example/ucp/v1/oauth/token';

    public function testReturnsNullWhenNoExtendedAuthMethodPresent(): void
    {
        $auth = $this->authenticator();
        $request = new Request();

        $clientId = $auth->authenticate($request, $this->salesChannel(), self::TOKEN_ENDPOINT, Context::createDefaultContext());

        static::assertNull(
            $clientId,
            'No client_assertion + no TLS — controller must fall back to League secret check.'
        );
    }

    public function testRejectsMalformedJwsShape(): void
    {
        $assertion = $this->b64('{"alg":"ES256","kid":"k"}') . '.' . $this->b64('{}');
        // missing the third dot segment

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion is not a well-formed JWS'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsUnsupportedAlgorithm(): void
    {
        $header = ['alg' => 'HS256', 'kid' => 'k'];
        $payload = $this->validPayload();
        $assertion = $this->b64(json_encode($header, \JSON_THROW_ON_ERROR))
            . '.' . $this->b64(json_encode($payload, \JSON_THROW_ON_ERROR))
            . '.signature';

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion algorithm must be ES256 or ES384'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsMissingKidHeader(): void
    {
        $header = ['alg' => 'ES256'];
        $assertion = $this->signed($header, $this->validPayload());

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion is missing the kid header'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsIssSubMismatch(): void
    {
        $payload = $this->validPayload();
        $payload['sub'] = 'different-from-iss';

        $assertion = $this->signed(['alg' => 'ES256', 'kid' => 'k'], $payload);

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion iss and sub must be identical and equal client_id'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsExpiredAssertion(): void
    {
        $payload = $this->validPayload();
        // Expired well beyond the 60s clock-skew tolerance.
        $payload['exp'] = time() - 600;

        $assertion = $this->signed(['alg' => 'ES256', 'kid' => 'k'], $payload);

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion is expired or missing exp'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsAudMismatch(): void
    {
        $payload = $this->validPayload();
        $payload['aud'] = 'https://attacker.example/different/endpoint';

        $assertion = $this->signed(['alg' => 'ES256', 'kid' => 'k'], $payload);

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion aud does not include the token endpoint URL'
        ));

        $this->authenticator()->authenticate(
            $this->jwtRequest($assertion),
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testRejectsTlsClientAuthWithoutClientId(): void
    {
        $request = new Request();
        $request->server->set('SSL_CLIENT_S_DN', 'CN=client-1,O=ExamplePlatform');

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'tls_client_auth requested but client_id missing'
        ));

        $this->authenticator()->authenticate(
            $request,
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );
    }

    public function testReturnsNullWhenAssertionTypeIsUnknown(): void
    {
        $request = new Request(request: [
            'client_assertion_type' => 'urn:custom:not-jwt-bearer',
            'client_assertion' => 'whatever',
        ]);

        $resolved = $this->authenticator()->authenticate(
            $request,
            $this->salesChannel(),
            self::TOKEN_ENDPOINT,
            Context::createDefaultContext()
        );

        static::assertNull($resolved);
    }

    private function authenticator(): ClientAuthenticator
    {
        return new ClientAuthenticator(
            $this->createMock(Connection::class),
            $this->createMock(PlatformProfileFetcher::class)
        );
    }

    private function jwtRequest(string $assertion): Request
    {
        return new Request(request: [
            'client_assertion_type' => ClientAuthenticator::ASSERTION_TYPE_JWT,
            'client_assertion' => $assertion,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'iss' => 'client-1',
            'sub' => 'client-1',
            'aud' => self::TOKEN_ENDPOINT,
            'jti' => 'jti-' . bin2hex(random_bytes(8)),
            'exp' => time() + 300,
            'iat' => time(),
        ];
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    private function signed(array $header, array $payload): string
    {
        return $this->b64(json_encode($header, \JSON_THROW_ON_ERROR))
            . '.' . $this->b64(json_encode($payload, \JSON_THROW_ON_ERROR))
            . '.signature-stub';
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function salesChannel(): string
    {
        return '00000000000000000000000000000000';
    }
}
