<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\Consent;
use Shopware\Core\System\Consent\DTO\ConsentState;

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

    public function testCreate(): void
    {
        $this->repository->create('test-consent', ConsentScope::GLOBAL);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM consent WHERE name = :name',
            ['name' => 'test-consent']
        );

        static::assertIsArray($result);
        static::assertSame('test-consent', $result['name']);
        static::assertSame(ConsentScope::GLOBAL->value, $result['scope']);
        static::assertIsString($result['id']);
        static::assertIsString($result['created_at']);
        static::assertNull($result['updated_at']);

        Uuid::fromBytesToHex($result['id']); // validate valid uuid
    }

    public function testCreateThrowsExceptionWhenNameAlreadyExists(): void
    {
        $this->repository->create('duplicate-consent', ConsentScope::GLOBAL);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "duplicate-consent" already exists.');

        $this->repository->create('duplicate-consent', ConsentScope::GLOBAL);
    }

    public function testFetchAllConsents(): void
    {
        $this->repository->create('consent-1', ConsentScope::GLOBAL);
        $this->repository->create('consent-2', ConsentScope::ADMIN_USER);

        $result = $this->repository->fetchAllConsents();

        static::assertCount(4, $result);
        static::assertContainsOnlyInstancesOf(Consent::class, $result);

        $names = array_column($result, 'name');
        static::assertContains('consent-1', $names);
        static::assertContains('consent-2', $names);
    }

    public function testUpdateConsentState(): void
    {
        $consent = array_find(
            $this->repository->fetchAllConsents(),
            fn (Consent $consent) => $consent->name === 'tracking_consent'
        );

        static::assertInstanceOf(Consent::class, $consent);

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($consent, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
    }

    public function testUpdateGlobalConsentState(): void
    {
        $consent = array_find(
            $this->repository->fetchAllConsents(),
            fn (Consent $consent) => $consent->name === 'backend_data_consent'
        );

        static::assertInstanceOf(Consent::class, $consent);

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($consent, null, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertNull($states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);
    }

    public function testUpdateConsentStateUpdatesExisting(): void
    {
        $consent = array_find(
            $this->repository->fetchAllConsents(),
            fn (Consent $consent) => $consent->name === 'tracking_consent'
        );

        static::assertInstanceOf(Consent::class, $consent);

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($consent, $userId, ConsentStatus::ACCEPTED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $states[0]->status);

        $this->repository->updateConsentState($consent, $userId, ConsentStatus::REVOKED, $userId);

        $states = $this->repository->fetchAllConsentStates();

        static::assertCount(1, $states);

        static::assertSame($userId, $states[0]->actorId);
        static::assertSame($userId, $states[0]->identifier);
        static::assertSame(ConsentStatus::REVOKED, $states[0]->status);
    }

    public function testUpdateConsentStateCreatesHistory(): void
    {
        $consent = array_find(
            $this->repository->fetchAllConsents(),
            fn (Consent $consent) => $consent->name === 'tracking_consent'
        );

        static::assertInstanceOf(Consent::class, $consent);

        $userId = Uuid::randomHex();
        $this->repository->updateConsentState($consent, $userId, ConsentStatus::ACCEPTED, $userId);
        $this->repository->updateConsentState($consent, $userId, ConsentStatus::REVOKED, $userId);

        $history = $this->repository->getHistory($consent->id, $userId);

        static::assertCount(2, $history);
        static::assertSame(ConsentStatus::REVOKED, $history[0]->status);
        static::assertSame($userId, $history[0]->actorId);
        static::assertSame($userId, $history[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $history[1]->status);
        static::assertSame($userId, $history[1]->actorId);
        static::assertSame($userId, $history[1]->identifier);
    }

    public function testFetchAllConsentStates(): void
    {
        $consents = $this->repository->fetchAllConsents();

        $trackingConsent = array_find($consents, fn (Consent $consent) => $consent->name === 'tracking_consent');
        $backendDataConsent = array_find($consents, fn (Consent $consent) => $consent->name === 'backend_data_consent');

        static::assertInstanceOf(Consent::class, $trackingConsent);
        static::assertInstanceOf(Consent::class, $backendDataConsent);

        $user1 = Uuid::randomHex();
        $this->repository->updateConsentState($backendDataConsent, null, ConsentStatus::ACCEPTED, $user1);

        $user2 = Uuid::randomHex();
        $this->repository->updateConsentState($trackingConsent, $user2, ConsentStatus::REVOKED, $user2);

        $result = $this->repository->fetchAllConsentStates();

        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ConsentState::class, $result);

        static::assertSame(ConsentScope::GLOBAL, $result[0]->scope);
        static::assertNull($result[0]->identifier);
        static::assertSame($user1, $result[0]->actorId);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);

        static::assertSame(ConsentScope::ADMIN_USER, $result[1]->scope);
        static::assertSame($user2, $result[1]->identifier);
        static::assertSame($user2, $result[1]->actorId);
        static::assertSame(ConsentStatus::REVOKED, $result[1]->status);
    }

    public function testGetHistoryReturnsEmptyArrayWhenNoHistory(): void
    {
        $consent = array_find(
            $this->repository->fetchAllConsents(),
            fn (Consent $consent) => $consent->name === 'tracking_consent'
        );

        static::assertInstanceOf(Consent::class, $consent);

        $history = $this->repository->getHistory($consent->id, null);

        static::assertSame([], $history);
    }
}
