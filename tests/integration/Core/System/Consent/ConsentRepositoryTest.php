<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
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
        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = new ConsentRepository($this->connection);
    }

    public function testUpdateConsentState(): void
    {
        $productAnalytics = new ProductAnalytics();

        $userId = Uuid::randomHex();
        $updatedState = $this->repository->updateConsentState($productAnalytics, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($productAnalytics->getName(), $states[0]->name);

        static::assertSame($productAnalytics->getName(), $updatedState->name);
        static::assertSame($productAnalytics->getScopeName(), $updatedState->scopeName);
        static::assertEquals($userId, $updatedState->actorId);
        static::assertEquals(ConsentStatus::ACCEPTED, $updatedState->status);
        static::assertEquals($userId, $updatedState->identifier);
    }

    public function testUpdateSystemConsentState(): void
    {
        $backendData = new BackendData();

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame('system', $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('backend_data', $states[0]->name);
    }

    public function testUpdateConsentStateUpdatesExisting(): void
    {
        $tracking = new ProductAnalytics();

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);

        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testFetchAllConsentStates(): void
    {
        $productAnalytics = new ProductAnalytics();
        $backendData = new BackendData();

        $user1 = Uuid::randomHex();
        $this->repository->updateConsentState($backendData, 'system', ConsentStatus::ACCEPTED, $user1);

        $user2 = Uuid::randomHex();
        $this->repository->updateConsentState($productAnalytics, $user2, ConsentStatus::REVOKED, $user2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentStateRecord::class, $result);

        static::assertSame('backend_data', $result[0]->name);
        static::assertSame('system', $result[0]->identifier);
        static::assertSame($user1, $result[0]->actorId);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame($productAnalytics->getName(), $result[1]->name);
        static::assertSame($user2, $result[1]->identifier);
        static::assertSame($user2, $result[1]->actorId);
        static::assertSame(ConsentStatus::REVOKED, $result[1]->status);
    }
}
