<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\UserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Sso\TokenService\TokenResult;
use Shopware\Core\Framework\Sso\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The database paths of the service are covered by the integration tests; this covers the token update,
 * which only maps values.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        $loginConfigService = new LoginConfigService([
            'use_default' => false,
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_uri' => 'http://redirect.uri',
            'base_url' => 'http://base.uri',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
        ], static::createStub(RouterInterface::class));
        $client = static::createStub(HttpClientInterface::class);
        $clock = new MockClock('2026-10-13 10:00:00');

        $this->userService = new UserService(
            static::createStub(Connection::class),
            new IdTokenParser(new PublicKeyLoader($client, $loginConfigService, new ArrayAdapter()), $loginConfigService, $clock),
            static::createStub(EntityRepository::class),
            new ExternalTokenService($client, $loginConfigService),
            $clock,
        );
    }

    public function testUpdateOAuthUserWithNewTokenCarriesTheAuthenticationTime(): void
    {
        $authenticatedAt = new \DateTimeImmutable('2026-10-13 09:59:30');
        $user = $this->createUser();

        $updated = $this->userService->updateOAuthUserWithNewToken($user, $this->createTokenResult(), $authenticatedAt);

        static::assertSame($user->id, $updated->id);
        static::assertSame($user->userId, $updated->userId);
        static::assertNotNull($updated->token);
        static::assertSame('new-access-token', $updated->token->token);
        static::assertSame('new-refresh-token', $updated->token->refreshToken);
        static::assertSame($authenticatedAt, $updated->authenticatedAt);
    }

    public function testUpdateOAuthUserWithNewTokenWithoutAnAuthenticationTimeLeavesItUnknown(): void
    {
        $updated = $this->userService->updateOAuthUserWithNewToken($this->createUser(), $this->createTokenResult());

        static::assertNull($updated->authenticatedAt);
    }

    private function createUser(): ExternalAuthUser
    {
        return ExternalAuthUser::create([
            'id' => 'id_value',
            'user_id' => 'user_id_value',
            'user_sub' => 'user_sub_value',
            'token' => ['token' => 'old-access-token', 'refreshToken' => 'old-refresh-token'],
            'expiry' => new \DateTimeImmutable('2026-10-13 09:00:00'),
            'email' => 'test@example.com',
        ]);
    }

    private function createTokenResult(): TokenResult
    {
        return TokenResult::createFromResponse((string) json_encode([
            'id_token' => 'id-token',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'scope',
        ]));
    }
}
