<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Definition\ProductAnalytics;
use Shopware\Core\System\Consent\DTO\ConsentStateRecord;

/**
 * @internal
 */
class ConsentRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ConsentRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get(ConsentRepository::class);
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testUpdateConsentState(): void
    {
        $productAnalytics = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $updatedState = $this->repository->updateConsentState($productAnalytics, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($productAnalytics->getName(), $states[0]->name);

        static::assertSame($productAnalytics->getName(), $updatedState->name);
        static::assertSame($productAnalytics->getScopeName(), $updatedState->scopeName);
        static::assertSame('test-user', $updatedState->actor);
        static::assertEquals(ConsentStatus::ACCEPTED, $updatedState->status);
        static::assertSame($userId, $updatedState->identifier);
    }

    public function testUpdateSystemConsentState(): void
    {
        $backendData = new BackendData();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame('system', $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('backend_data', $states[0]->name);
    }

    public function testUpdateConsentStateUpdatesExisting(): void
    {
        $tracking = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);

        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testFetchAllConsentStates(): void
    {
        $productAnalytics = new ProductAnalytics();
        $backendData = new BackendData();

        $user1 = $this->createUser('first-user');
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $user1);

        $user2 = $this->createUser('second-user');
        $this->repository->updateConsentState($productAnalytics, $user2, ConsentStatus::REVOKED, $user2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentStateRecord::class, $result);

        static::assertSame('backend_data', $result[0]->name);
        static::assertSame('system', $result[0]->identifier);
        static::assertSame('first-user', $result[0]->actor);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame($productAnalytics->getName(), $result[1]->name);
        static::assertSame($user2, $result[1]->identifier);
        static::assertSame('second-user', $result[1]->actor);
        static::assertSame(ConsentStatus::REVOKED, $result[1]->status);
    }

    public function testGetPreviousLoggedStateReturnsNullWhenNoMatchingLogExists(): void
    {
        $this->connection->executeStatement('DELETE FROM consent_log');

        static::assertNull(
            $this->repository->getPreviousLoggedState(
                BackendData::NAME,
                'system',
                new \DateTimeImmutable('2024-01-01 10:00:00.000')
            )
        );
    }

    public function testGetPreviousLoggedStateReturnsMostRecentStateAtOrBeforeDate(): void
    {
        $this->connection->executeStatement('DELETE FROM consent_log');

        $this->insertConsentLog(BackendData::NAME, 'system', ConsentStatus::ACCEPTED, '2024-01-01 09:00:00.000');
        $this->insertConsentLog(BackendData::NAME, 'system', ConsentStatus::REVOKED, '2024-01-02 09:00:00.000');

        static::assertSame(
            ConsentStatus::REVOKED,
            $this->repository->getPreviousLoggedState(
                BackendData::NAME,
                'system',
                new \DateTimeImmutable('2024-01-02 09:00:00.000')
            )
        );
    }

    public function testGetPreviousLoggedStateExcludesCurrentStateAtSameTimestamp(): void
    {
        $this->connection->executeStatement('DELETE FROM consent_log');

        $this->insertConsentLog(BackendData::NAME, 'system', ConsentStatus::ACCEPTED, '2024-01-01 09:00:00.000');
        $this->insertConsentLog(BackendData::NAME, 'system', ConsentStatus::REVOKED, '2024-01-02 09:00:00.000');

        static::assertSame(
            ConsentStatus::ACCEPTED,
            $this->repository->getPreviousLoggedState(
                BackendData::NAME,
                'system',
                new \DateTimeImmutable('2024-01-02 09:00:00.000'),
                ConsentStatus::REVOKED
            )
        );
    }

    public function testGetPreviousLoggedStateReturnsNullForUnknownAction(): void
    {
        $this->connection->executeStatement('DELETE FROM consent_log');

        $this->connection->insert('consent_log', [
            'consent_name' => BackendData::NAME,
            'timestamp' => '2024-01-01 09:00:00.000',
            'message' => \json_encode([
                'action' => 'requested',
                'identifier' => 'system',
                'actor' => 'actor',
            ], \JSON_THROW_ON_ERROR),
        ]);

        static::assertNull(
            $this->repository->getPreviousLoggedState(
                BackendData::NAME,
                'system',
                new \DateTimeImmutable('2024-01-01 09:00:00.000')
            )
        );
    }

    private function createUser(string $name): string
    {
        $userId = Uuid::randomHex();
        $userRepo = $this->getContainer()->get('user.repository');

        $userRepo->create([
            [
                'id' => $userId,
                'username' => $name,
                'firstName' => 'Test',
                'lastName' => 'User',
                'email' => $name . '@example.com',
                'password' => 'shopware',
                'locale' => [
                    'code' => 'locale-' . $name,
                    'name' => 'Test Locale',
                    'territory' => 'Test Territory',
                ],
                'title' => null,
                'admin' => true,
            ],
        ], Context::createDefaultContext());

        return $userId;
    }

    private function insertConsentLog(string $consentName, string $identifier, ConsentStatus $status, string $timestamp): void
    {
        $this->connection->insert('consent_log', [
            'consent_name' => $consentName,
            'timestamp' => $timestamp,
            'message' => \json_encode([
                'action' => $status->value,
                'identifier' => $identifier,
                'actor' => 'actor',
            ], \JSON_THROW_ON_ERROR),
        ]);
    }
}
