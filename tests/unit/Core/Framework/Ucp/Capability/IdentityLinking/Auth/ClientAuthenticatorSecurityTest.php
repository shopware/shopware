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
 * @internal
 */
#[CoversClass(ClientAuthenticator::class)]
class ClientAuthenticatorSecurityTest extends TestCase
{
    public function testPrivateKeyJwtRequiresJtiForReplayProtection(): void
    {
        $authenticator = new ClientAuthenticator(
            $this->createMock(Connection::class),
            $this->createMock(PlatformProfileFetcher::class)
        );

        $assertion = $this->unsignedAssertion([
            'iss' => 'client-1',
            'sub' => 'client-1',
            'aud' => 'https://shop.example/ucp/v1/oauth/token',
            'exp' => time() + 60,
            // jti intentionally omitted
        ]);

        $request = new Request(request: [
            'client_assertion_type' => ClientAuthenticator::ASSERTION_TYPE_JWT,
            'client_assertion' => $assertion,
        ]);

        $this->expectExceptionObject(UcpException::oauthClientAuthFailed(
            'client_assertion is missing the required `jti` claim (replay protection)'
        ));

        $authenticator->authenticate(
            $request,
            '00000000000000000000000000000000',
            'https://shop.example/ucp/v1/oauth/token',
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function unsignedAssertion(array $payload): string
    {
        $header = ['alg' => 'ES256', 'kid' => 'kid-1', 'typ' => 'JWT'];

        return $this->b64(json_encode($header, \JSON_THROW_ON_ERROR))
            . '.'
            . $this->b64(json_encode($payload, \JSON_THROW_ON_ERROR))
            . '.'
            . $this->b64(str_repeat("\0", 64));
    }

    private function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
