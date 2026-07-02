<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Verifier;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\PasswordVerifier;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(PasswordVerifier::class)]
class PasswordVerifierTest extends TestCase
{
    public function testSupports(): void
    {
        $verifier = $this->createVerifier(null);

        static::assertTrue($verifier->supports('password'));
        static::assertFalse($verifier->supports('totp'));
        static::assertFalse($verifier->supports('webauthn'));
    }

    public function testVerifyPrimaryWithCorrectCredentials(): void
    {
        $userId = Uuid::randomBytes();
        $verifier = $this->createVerifier([
            'id' => $userId,
            'password' => password_hash('secure-test', \PASSWORD_BCRYPT),
        ]);

        $result = $verifier->verifyPrimary(['username' => 'admin', 'password' => 'secure-test']);

        static::assertSame(Uuid::fromBytesToHex($userId), $result);
    }

    public function testVerifyPrimaryWithWrongPasswordThrows(): void
    {
        $verifier = $this->createVerifier([
            'id' => Uuid::randomBytes(),
            'password' => password_hash('secure-test', \PASSWORD_BCRYPT),
        ]);

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['username' => 'admin', 'password' => 'wrong-password']);
    }

    public function testVerifyPrimaryWithUnknownUserThrows(): void
    {
        $verifier = $this->createVerifier(null);

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['username' => 'ghost', 'password' => 'whatever']);
    }

    public function testVerifyPrimaryWithMissingUsernameThrows(): void
    {
        $verifier = $this->createVerifier(null);

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['password' => 'whatever']);
    }

    public function testVerifyPrimaryWithMissingPasswordThrows(): void
    {
        $verifier = $this->createVerifier(null);

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['username' => 'admin']);
    }

    public function testVerifyPrimaryThrowsWhenPasswordMethodIsDisabled(): void
    {
        $methodSettings = new MethodSettingsService(new StaticSystemConfigService(), [], false);
        $verifier = $this->createVerifier(
            ['id' => Uuid::randomBytes(), 'password' => password_hash('secure-test', \PASSWORD_BCRYPT)],
            $methodSettings
        );

        $this->expectException(OAuthServerException::class);

        $verifier->verifyPrimary(['username' => 'admin', 'password' => 'secure-test']);
    }

    /**
     * @param array{id: string, password: string}|null $userRow
     */
    private function createVerifier(?array $userRow, ?MethodSettingsService $methodSettings = null): PasswordVerifier
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('fetchAssociative')->willReturn($userRow ?? false);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new PasswordVerifier(
            $connection,
            $methodSettings ?? new MethodSettingsService(new StaticSystemConfigService())
        );
    }
}
