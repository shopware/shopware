<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent;

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

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get(ConsentRepository::class);
    }

    public function testUpdateConsentState(): void
    {
        $productAnalytics = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($productAnalytics, $userId, ConsentStatus::ACCEPTED, $userId, '2026-02-01');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame($productAnalytics->getName(), $states[0]->name);
        static::assertSame('2026-02-01', $states[0]->revision);
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
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId, '1.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
        static::assertSame('1.0.0', $states[0]->revision);

        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testRevokeConsentStateClearsPassedRevision(): void
    {
        $tracking = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId, '2.0.0');

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
        static::assertNull($states[0]->revision);
    }

    public function testInitializesRevokedConsentsWithDeclinedState(): void
    {
        $tracking = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testDoesNotOverrideDeclinedStateWithRevokedState(): void
    {
        $tracking = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame('test-user', $states[0]->actor);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::DECLINED, $states[0]->status);
    }

    public function testSetsRevokedStateIfStateWasAccepted(): void
    {
        $tracking = new ProductAnalytics();

        $userId = $this->createUser('test-user');
        $this->repository->updateConsentState($tracking, $userId, ConsentStatus::ACCEPTED, $userId);
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
        static::assertSame(ConsentStatus::DECLINED, $result[1]->status);
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
                    'code' => 'de-DE-' . $name,
                    'name' => 'Test Locale',
                    'territory' => 'Test Territory',
                ],
                'title' => null,
                'admin' => true,
            ],
        ], Context::createDefaultContext());

        return $userId;
    }
}
