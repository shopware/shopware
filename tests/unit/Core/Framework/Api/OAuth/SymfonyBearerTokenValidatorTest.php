<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SymfonyBearerTokenValidator::class)]
class SymfonyBearerTokenValidatorTest extends TestCase
{
    // this is a valid token, generated for the test app secret
    private const VALID_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJ0ZXN0IiwianRpIjoiMDE4ZDlkY2NlMDBhNzA0YWIwMzRlYzA2OTQ5ODFlZDUiLCJpYXQiOjE3MDc3NDk0NjYuMTIyNzU2LCJuYmYiOjE3MDc3NDk0NjYuMTIyNzU2LCJleHAiOjQ4NjM0MjMwNjYuMTIyNDksInN1YiI6IjAxOGQ5ZGNjZTAwYTcwNGFiMDM0ZWMwNjk0OTgxZWQ1Iiwic2NvcGVzIjpbXX0.GnFYQ-VTo7zKnK9-M3m9v4FnugAtNp75kcb8mpxscwY';

    private const OAUTH_USER_ID = '018d9dcce00a704ab034ec0694981ed5';

    #[DataProvider('dataProviderInvalidRequests')]
    public function testInvalidRequests(Request $request): void
    {
        $validator = new SymfonyBearerTokenValidator(
            static::createStub(AccessTokenRepositoryInterface::class),
            static::createStub(Connection::class),
            $this->getJwtConfiguration()
        );

        $this->expectExceptionObject(OAuthServerException::accessDenied());

        $validator->validateAuthorization($request);
    }

    public function testRevokedToken(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $accessTokenRepository = static::createStub(AccessTokenRepositoryInterface::class);
        $accessTokenRepository
            ->method('isAccessTokenRevoked')
            ->willReturn(true);

        $validator = new SymfonyBearerTokenValidator(
            $accessTokenRepository,
            static::createStub(Connection::class),
            $this->getJwtConfiguration()
        );

        $this->expectExceptionObject(OAuthServerException::accessDenied());

        $validator->validateAuthorization($request);
    }

    public function testValidTokenYieldsAttributes(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            static::createStub(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(null),
            $this->getJwtConfiguration()
        );

        $validator->validateAuthorization($request);

        static::assertSame(self::OAUTH_USER_ID, $request->attributes->get('oauth_user_id'));
        static::assertSame(self::OAUTH_USER_ID, $request->attributes->get('oauth_access_token_id'));
        static::assertSame('test', $request->attributes->get('oauth_client_id'));
        static::assertSame([], $request->attributes->get('oauth_scopes'));
    }

    public function testUserDeleted(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            static::createStub(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(false),
            $this->getJwtConfiguration()
        );

        $this->expectExceptionObject(OAuthServerException::accessDenied());

        $validator->validateAuthorization($request);
    }

    public function testInactiveUser(): void
    {
        $request = new Request(server: ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            static::createStub(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(null, false),
            $this->getJwtConfiguration()
        );

        $this->expectExceptionObject(OAuthServerException::accessDenied());

        $validator->validateAuthorization($request);
    }

    /**
     * Last password change is now, so the generated token must be expired
     */
    public function testExpired(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            static::createStub(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(date('Y-m-d H:i:s')),
            $this->getJwtConfiguration()
        );

        $this->expectExceptionObject(OAuthServerException::accessDenied());

        $validator->validateAuthorization($request);
    }

    public static function dataProviderInvalidRequests(): \Generator
    {
        yield 'missing header' => [
            new Request(),
        ];

        yield 'invalid header' => [
            new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ']),
        ];

        yield 'invalid token' => [
            new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer blablaa']),
        ];

        yield 'valid token, but not signed by us' => [
            new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c']),
        ];
    }

    private function getJwtConfiguration(): Configuration
    {
        $key = InMemory::plainText('testtesttesttesttesttesttesttesttesttesttesttesttesttesttest');
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            $key
        );

        return $config->withValidationConstraints(new SignedWith(new Sha256(), $key));
    }

    private function getConnectionMock(mixed $returnValue, bool $active = true): Connection&Stub
    {
        $connection = static::createStub(Connection::class);

        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')
            ->willReturnCallback(static function () use ($returnValue, $active): array|false {
                if ($returnValue === false) {
                    return false;
                }

                return [
                    'last_updated_password_at' => $returnValue,
                    'active' => $active,
                ];
            });

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
