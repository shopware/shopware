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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SymfonyBearerTokenValidator::class)]
class SymfonyBearerTokenValidatorTest extends TestCase
{
    // this is a valid token, generated for the test app secret
    private const VALID_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJ0ZXN0IiwianRpIjoiMDE4ZDlkY2NlMDBhNzA0YWIwMzRlYzA2OTQ5ODFlZDUiLCJpYXQiOjE3MDc3NDk0NjYuMTIyNzU2LCJuYmYiOjE3MDc3NDk0NjYuMTIyNzU2LCJleHAiOjQ4NjM0MjMwNjYuMTIyNDksInN1YiI6IjAxOGQ5ZGNjZTAwYTcwNGFiMDM0ZWMwNjk0OTgxZWQ1Iiwic2NvcGVzIjpbXX0.GnFYQ-VTo7zKnK9-M3m9v4FnugAtNp75kcb8mpxscwY';

    private const TOKEN_USER_ID = '018d9dcce00a704ab034ec0694981ed5';

    #[DataProvider('dataProviderInvalidRequests')]
    public function testInvalidRequests(Request $request): void
    {
        $validator = new SymfonyBearerTokenValidator(
            $this->createMock(AccessTokenRepositoryInterface::class),
            $this->createMock(Connection::class),
            $this->getJwtConfiguration()
        );

        static::expectException(OAuthServerException::class);
        static::expectExceptionMessage('The resource owner or authorization server denied the request.');

        $validator->validateAuthorization($request);
    }

    public function testRevokedToken(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository
            ->method('isAccessTokenRevoked')
            ->with(self::TOKEN_USER_ID)
            ->willReturn(true);

        $validator = new SymfonyBearerTokenValidator(
            $accessTokenRepository,
            $this->createMock(Connection::class),
            $this->getJwtConfiguration()
        );

        static::expectException(OAuthServerException::class);
        static::expectExceptionMessage('The resource owner or authorization server denied the request.');

        $validator->validateAuthorization($request);
    }

    public function testValidTokenYieldsAttributes(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            $this->createMock(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(null),
            $this->getJwtConfiguration()
        );

        $validator->validateAuthorization($request);

        static::assertEquals(self::TOKEN_USER_ID, $request->attributes->get('oauth_user_id'));
        static::assertEquals(self::TOKEN_USER_ID, $request->attributes->get('oauth_access_token_id'));
        static::assertEquals('test', $request->attributes->get('oauth_client_id'));
        static::assertEquals([], $request->attributes->get('oauth_scopes'));
    }

    public function testUserDeleted(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            $this->createMock(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(false),
            $this->getJwtConfiguration()
        );

        static::expectException(OAuthServerException::class);
        static::expectExceptionMessage('The resource owner or authorization server denied the request.');

        $validator->validateAuthorization($request);
    }

    /**
     * Last password change is now, so the generated token must be expired
     */
    public function testExpired(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_authorization' => 'Bearer ' . self::VALID_TOKEN]);

        $validator = new SymfonyBearerTokenValidator(
            $this->createMock(AccessTokenRepositoryInterface::class),
            $this->getConnectionMock(date('Y-m-d H:i:s')),
            $this->getJwtConfiguration()
        );

        static::expectException(OAuthServerException::class);
        static::expectExceptionMessage('The resource owner or authorization server denied the request.');

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

        $config->setValidationConstraints(new SignedWith(new Sha256(), $key));

        return $config;
    }

    private function getConnectionMock(mixed $returnValue): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);

        $result = $this->createMock(Result::class);
        $result->method('fetchOne')
            ->willReturn($returnValue);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
