<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminSecondFactorGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository;

/**
 * @internal
 */
#[CoversClass(ScopeRepository::class)]
class ScopeRepositoryTest extends TestCase
{
    public function testGetScopeEntityByIdentifierReturnsRegisteredScope(): void
    {
        $writeScope = new WriteScope();
        $repository = $this->createScopeRepository(scopes: [$writeScope]);

        static::assertSame($writeScope, $repository->getScopeEntityByIdentifier('write'));
        static::assertNull($repository->getScopeEntityByIdentifier('unknown'));
    }

    public function testMfaPendingScopeIsResolved(): void
    {
        $repository = $this->createScopeRepository();

        $scope = $repository->getScopeEntityByIdentifier(MfaPendingScope::IDENTIFIER);

        static::assertInstanceOf(MfaPendingScope::class, $scope);
        static::assertSame(MfaPendingScope::IDENTIFIER, $scope->getIdentifier());
    }

    public function testChallengeMarkerScopeIsResolvedWithItsIdentifier(): void
    {
        $repository = $this->createScopeRepository();

        $scope = $repository->getScopeEntityByIdentifier(AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX . 'abc');

        static::assertInstanceOf(MfaPendingScope::class, $scope);
        static::assertSame('admin-mfa-challenge:abc', $scope->getIdentifier());
    }

    public function testMethodsMarkerScopeIsResolvedWithItsIdentifier(): void
    {
        $repository = $this->createScopeRepository();

        $scope = $repository->getScopeEntityByIdentifier(AdminPrimaryGrant::METHODS_SCOPE_PREFIX . 'totp,recovery_codes');

        static::assertInstanceOf(MfaPendingScope::class, $scope);
        static::assertSame('admin-mfa-methods:totp,recovery_codes', $scope->getIdentifier());
    }

    public function testPasswordGrantGetsWriteScopeAndKeepsUserVerified(): void
    {
        $repository = $this->createScopeRepository();

        $scopes = $repository->finalizeScopes(
            [new UserVerifiedScope()],
            ScopeRepository::PASSWORD_GRANT,
            $this->createMock(ClientEntityInterface::class),
            'user-id'
        );

        static::assertSame(
            [UserVerifiedScope::IDENTIFIER, WriteScope::IDENTIFIER],
            $this->identifiers($scopes)
        );
    }

    public function testAdminPrimaryGrantIsTreatedLikePasswordGrant(): void
    {
        $repository = $this->createScopeRepository();

        $scopes = $repository->finalizeScopes(
            [new UserVerifiedScope()],
            AdminPrimaryGrant::TYPE,
            $this->createMock(ClientEntityInterface::class),
            'user-id'
        );

        static::assertSame(
            [UserVerifiedScope::IDENTIFIER, WriteScope::IDENTIFIER],
            $this->identifiers($scopes)
        );
    }

    public function testAdminSecondFactorGrantIsTreatedLikePasswordGrant(): void
    {
        $repository = $this->createScopeRepository();

        $scopes = $repository->finalizeScopes(
            [new UserVerifiedScope()],
            AdminSecondFactorGrant::TYPE,
            $this->createMock(ClientEntityInterface::class),
            'user-id'
        );

        static::assertSame(
            [UserVerifiedScope::IDENTIFIER, WriteScope::IDENTIFIER],
            $this->identifiers($scopes)
        );
    }

    public function testAdminPrimaryGrantForAdminUserGetsAdminScope(): void
    {
        $repository = $this->createScopeRepository(isAdmin: true);

        $scopes = $repository->finalizeScopes(
            [],
            AdminPrimaryGrant::TYPE,
            $this->createMock(ClientEntityInterface::class),
            'user-id'
        );

        static::assertSame(
            [WriteScope::IDENTIFIER, AdminScope::IDENTIFIER],
            $this->identifiers($scopes)
        );
    }

    public function testRefreshTokenGrantDropsUserVerifiedButKeepsWrite(): void
    {
        $repository = $this->createScopeRepository();

        $scopes = $repository->finalizeScopes(
            [new UserVerifiedScope(), new WriteScope()],
            ScopeRepository::REFRESH_TOKEN_GRANT,
            $this->createMock(ClientEntityInterface::class),
            'user-id'
        );

        static::assertSame([WriteScope::IDENTIFIER], $this->identifiers($scopes));
    }

    /**
     * @param list<ScopeEntityInterface> $scopes
     */
    private function createScopeRepository(array $scopes = [], bool $isAdmin = false): ScopeRepository
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn($isAdmin ? '1' : false);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new ScopeRepository($scopes, $connection);
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     *
     * @return list<string>
     */
    private function identifiers(array $scopes): array
    {
        return array_values(array_map(
            static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $scopes
        ));
    }
}
