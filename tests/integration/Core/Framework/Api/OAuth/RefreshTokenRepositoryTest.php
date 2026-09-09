<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AccessToken;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\RefreshToken;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class RefreshTokenRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private RefreshTokenRepository $repository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock('2026-09-09 12:00:00');
        $this->repository = new RefreshTokenRepository($this->connection, $this->clock);
    }

    public function testReusingRotatedTokenRevokesItsFamily(): void
    {
        $oldToken = $this->createRefreshToken();
        $this->repository->persistNewRefreshToken($oldToken);

        $familyId = $this->repository->getRefreshTokenFamilyId($oldToken->getIdentifier());
        static::assertNotNull($familyId);

        $successor = $this->createRefreshToken();
        $successor->setFamilyId($familyId);
        $this->repository->persistNewRefreshToken($successor);
        $this->repository->revokeRefreshToken($oldToken->getIdentifier());

        static::assertNotFalse($this->connection->fetchOne(
            'SELECT revoked_at FROM refresh_token WHERE token_id = :tokenId',
            ['tokenId' => $oldToken->getIdentifier()]
        ));
        static::assertTrue($this->repository->isRefreshTokenRevoked($oldToken->getIdentifier()));
        static::assertTrue($this->repository->isRefreshTokenRevoked($successor->getIdentifier()));
    }

    public function testLegacyTokenGetsFamilyWhenValidated(): void
    {
        $token = $this->createRefreshToken();
        $this->repository->persistNewRefreshToken($token);
        $this->connection->update('refresh_token', ['family_id' => null], ['token_id' => $token->getIdentifier()]);

        static::assertFalse($this->repository->isRefreshTokenRevoked($token->getIdentifier()));
        static::assertNotNull($this->repository->getRefreshTokenFamilyId($token->getIdentifier()));
    }

    private function createRefreshToken(): RefreshToken
    {
        $accessToken = new AccessToken(
            new ApiClient('shopware-cli', true, confidential: false),
            [],
            Uuid::randomHex()
        );

        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier('refresh-' . Uuid::randomHex());
        $refreshToken->setAccessToken($accessToken);
        $refreshToken->setExpiryDateTime($this->clock->now()->modify('+1 hour'));

        return $refreshToken;
    }
}
