<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentStateLogRecord;
use Shopware\Core\System\Consent\DTO\ConsentStateRecord;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentService::class)]
class ConsentServiceTest extends TestCase
{
    private MockObject&ConsentRepository $consentRepository;

    protected function setUp(): void
    {
        $this->consentRepository = $this->createMock(ConsentRepository::class);
    }

    public function testList(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
            'consent-2' => ConsentScope::ADMIN_USER,
        ]);

        $record1 = new ConsentStateRecord('consent-1', null, ConsentStatus::ACCEPTED, 'user-123');
        $record2 = new ConsentStateRecord('consent-2', 'user-123', ConsentStatus::REQUESTED, 'user-123');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$record1, $record2]);

        $context = (new ConsentContext())
            ->add(ConsentScope::ADMIN_USER, 'user-123');

        $result = $service->list($context);

        static::assertCount(2, $result);
        static::assertSame('consent-1', $result['consent-1']->name);
        static::assertSame(ConsentStatus::ACCEPTED, $result['consent-1']->status);
        static::assertSame('consent-2', $result['consent-2']->name);
        static::assertSame(ConsentStatus::REQUESTED, $result['consent-2']->status);
    }

    public function testListCachesConsents(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $context = (new ConsentContext())
            ->add(ConsentScope::ADMIN_USER, 'user-123');

        $service->list($context);
        $service->list($context);
    }

    public function testGetConsentStatusThrowsExceptionWhenNoIdentifierGivenForAdminScope(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::ADMIN_USER,
        ]);

        self::expectExceptionObject(ConsentException::identifierRequired());

        $service->getConsentState('consent-1', null);
    }

    public function testGetConsentStatus(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $record = new ConsentStateRecord('consent-1', null, ConsentStatus::ACCEPTED, 'user-123');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$record]);

        $result = $service->getConsentState('consent-1', null);

        static::assertSame('consent-1', $result->name);
        static::assertSame(ConsentScope::GLOBAL, $result->scope);
        static::assertNull($result->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $result->status);
        static::assertSame('user-123', $result->actorId);
    }

    public function testGetConsentStatusReturnsRequestedStateByDefault(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $result = $service->getConsentState('consent-1', null);

        static::assertSame('consent-1', $result->name);
        static::assertSame(ConsentStatus::REQUESTED, $result->status);
        static::assertNull($result->identifier);
        static::assertNull($result->actorId);
    }

    public function testGetConsentStatusThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->getConsentState('non-existent', 'user-123');
    }

    public function testAcceptConsentIsNoopWhenConsentAlreadyAccepted(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([new ConsentStateRecord('consent-1', null, ConsentStatus::ACCEPTED, 'user-123')]);

        $this->consentRepository
            ->expects($this->never())
            ->method('updateConsentState');

        $service->acceptConsent('consent-1', 'user-123');
    }

    public function testAcceptConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentAcceptedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $this->consentRepository
            ->expects($this->once())
            ->method('updateConsentState')
            ->with(
                static::callback(fn (ConsentDefinition $consent) => $consent->getName() === 'consent-1'),
                null,
                ConsentStatus::ACCEPTED,
                'user-123'
            );

        $service->acceptConsent('consent-1', 'user-123');
    }

    public function testAcceptConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->acceptConsent('non-existent', 'user-123');
    }

    public function testRevokeConsentIsNoopWhenConsentAlreadyRevoked(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([new ConsentStateRecord('consent-1', null, ConsentStatus::REVOKED, 'user-123')]);

        $this->consentRepository
            ->expects($this->never())
            ->method('updateConsentState');

        $service->revokeConsent('consent-1', 'user-123');
    }

    public function testRevokeConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentRevokedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $this->consentRepository
            ->expects($this->once())
            ->method('updateConsentState')
            ->with(
                static::callback(fn (ConsentDefinition $consent) => $consent->getName() === 'consent-1'),
                null,
                ConsentStatus::REVOKED,
                'user-456'
            );

        $service->revokeConsent('consent-1', 'user-456');
    }

    public function testRevokeConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->revokeConsent('non-existent', 'user-123');
    }

    public function testGetHistoryThrowsExceptionWhenNoIdentifierGivenForAdminScope(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::ADMIN_USER,
        ]);

        self::expectExceptionObject(ConsentException::identifierRequired());

        $service->getHistory('consent-1', null);
    }

    public function testGetHistory(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope::GLOBAL,
        ]);

        $history = [
            new ConsentStateLogRecord(
                ConsentStatus::ACCEPTED,
                null,
                'user-123',
                new \DateTimeImmutable('2024-01-15 10:00:00')
            ),
            new ConsentStateLogRecord(
                ConsentStatus::REQUESTED,
                null,
                'user-123',
                new \DateTimeImmutable('2024-01-10 10:00:00')
            ),
        ];

        $this->consentRepository
            ->expects($this->once())
            ->method('getHistory')
            ->with('consent-1', null)
            ->willReturn($history);

        $result = $service->getHistory('consent-1');

        static::assertCount(2, $result);
        static::assertSame(ConsentStatus::ACCEPTED, $result[0]->status);
        static::assertSame('user-123', $result[0]->actorId);
        static::assertNull($result[0]->identifier);
        static::assertSame(ConsentStatus::REQUESTED, $result[1]->status);
        static::assertSame('user-123', $result[1]->actorId);
        static::assertNull($result[1]->identifier);
    }

    public function testGetHistoryWithAdminUserScope(): void
    {
        $service = $this->createService(null, [
            'consent-2' => ConsentScope::ADMIN_USER,
        ]);

        $history = [
            new ConsentStateLogRecord(
                ConsentStatus::REVOKED,
                'user-123',
                'user-123',
                new \DateTimeImmutable('2024-01-20 15:30:00')
            ),
            new ConsentStateLogRecord(
                ConsentStatus::ACCEPTED,
                'user-123',
                'user-123',
                new \DateTimeImmutable('2024-01-15 10:00:00')
            ),
        ];

        $this->consentRepository
            ->expects($this->once())
            ->method('getHistory')
            ->with('consent-2', 'user-123')
            ->willReturn($history);

        $result = $service->getHistory('consent-2', 'user-123');

        static::assertCount(2, $result);
        static::assertSame(ConsentStatus::REVOKED, $result[0]->status);
        static::assertSame('user-123', $result[0]->actorId);
        static::assertSame('user-123', $result[0]->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $result[1]->status);
        static::assertSame('user-123', $result[1]->actorId);
        static::assertSame('user-123', $result[1]->identifier);
    }

    public function testGetHistoryThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->getHistory('non-existent', 'user-123');
    }

    /**
     * @param array<string, ConsentScope> $consents
     */
    private function createService(?EventDispatcher $eventDispatcher = null, array $consents = []): ConsentService
    {
        $definitions = [];
        foreach ($consents as $name => $scope) {
            $definitions[] = new class($name, $scope) implements ConsentDefinition {
                public function __construct(
                    private readonly string $name,
                    private readonly ConsentScope $scope
                ) {
                }

                public function getName(): string
                {
                    return $this->name;
                }

                public function getScope(): ConsentScope
                {
                    return $this->scope;
                }

                public function getSince(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable();
                }
            };
        }

        return new ConsentService($definitions, $this->consentRepository, $eventDispatcher ?? new EventDispatcher());
    }
}
