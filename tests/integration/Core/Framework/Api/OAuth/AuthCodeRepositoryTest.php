<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\AuthCode;
use Shopware\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class AuthCodeRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private AuthCodeRepository $repository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock('2026-09-09 12:00:00');
        $this->repository = new AuthCodeRepository($this->connection, $this->clock);
    }

    public function testNewAuthCodeIsAnAuthCodeEntity(): void
    {
        static::assertInstanceOf(AuthCode::class, $this->repository->getNewAuthCode());
    }

    public function testPersistedCodeIsValidUntilRevoked(): void
    {
        $authCode = $this->createAuthCode('code-' . Uuid::randomHex(), '+5 minutes');

        static::assertTrue($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));

        $this->repository->persistNewAuthCode($authCode);
        static::assertFalse($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));

        $stored = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(user_id)) AS user_id, client_id FROM oauth_auth_code WHERE code_id = :codeId',
            ['codeId' => $authCode->getIdentifier()]
        );
        static::assertIsArray($stored);
        static::assertSame($authCode->getUserIdentifier(), $stored['user_id']);
        static::assertSame('shopware-cli', $stored['client_id']);

        $this->repository->revokeAuthCode($authCode->getIdentifier());
        static::assertTrue($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));
    }

    public function testExpiredCodesAreCleanedUp(): void
    {
        $expired = $this->createAuthCode('code-' . Uuid::randomHex(), '+1 minute');
        $this->repository->persistNewAuthCode($expired);
        static::assertFalse($this->repository->isAuthCodeRevoked($expired->getIdentifier()));

        $this->clock->modify('+2 minutes');

        static::assertTrue($this->repository->isAuthCodeRevoked($expired->getIdentifier()));
        static::assertFalse($this->connection->fetchOne(
            'SELECT 1 FROM oauth_auth_code WHERE code_id = :codeId',
            ['codeId' => $expired->getIdentifier()]
        ));
    }

    public function testCodesWithoutUserAreNotPersisted(): void
    {
        $authCode = new AuthCode();
        $authCode->setIdentifier('code-' . Uuid::randomHex());
        $authCode->setClient(new ApiClient('shopware-cli', true, confidential: false));
        $authCode->setExpiryDateTime($this->clock->now()->modify('+5 minutes'));

        $this->repository->persistNewAuthCode($authCode);

        static::assertTrue($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));
    }

    /**
     * @param non-empty-string $identifier
     */
    private function createAuthCode(string $identifier, string $expiresIn): AuthCode
    {
        $authCode = new AuthCode();
        $authCode->setIdentifier($identifier);
        $authCode->setUserIdentifier(Uuid::randomHex());
        $authCode->setClient(new ApiClient('shopware-cli', true, confidential: false));
        $authCode->setExpiryDateTime($this->clock->now()->modify($expiresIn));

        return $authCode;
    }
}
