<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\Consent;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateHistoryItem;
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
        $service = $this->createService();

        $consent1 = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);
        $consent2 = new Consent('id-2', 'consent-2', ConsentScope::ADMIN_USER, new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent1, $consent2]);

        $state1 = new ConsentState('consent-1', ConsentScope::GLOBAL, null, ConsentStatus::ACCEPTED, 'user-123');
        $state2 = new ConsentState('consent-2', ConsentScope::ADMIN_USER, 'user-123', ConsentStatus::REQUESTED, 'user-123');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$state1, $state2]);

        $context = (new ConsentContext())
            ->add(ConsentScope::ADMIN_USER, 'user-123');

        $result = $service->list($context);

        static::assertCount(2, $result);
        static::assertSame($state1, $result['consent-1']);
        static::assertSame($state2, $result['consent-2']);
    }

    public function testListCachesConsents(): void
    {
        $service = $this->createService();

        $consent = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $context = (new ConsentContext())
            ->add(ConsentScope::ADMIN_USER, 'user-123');

        $service->list($context);
        $service->list($context);
    }

    public function testGetConsentStatus(): void
    {
        $service = $this->createService();

        $consent = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $state1 = new ConsentState('consent-1', ConsentScope::GLOBAL, null, ConsentStatus::ACCEPTED, 'user-123');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$state1]);

        $result = $service->getConsentStatus('consent-1', null);

        static::assertSame($state1, $result);
    }

    public function testGetConsentStatusThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([]);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->getConsentStatus('non-existent', 'user-123');
    }

    public function testAcceptConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentAcceptedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher);

        $consent = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $this->consentRepository
            ->expects($this->once())
            ->method('updateConsentState')
            ->with($consent, null, ConsentStatus::ACCEPTED, 'user-123');

        $service->acceptConsent('consent-1', 'user-123');
    }

    public function testAcceptConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([]);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->acceptConsent('non-existent', 'user-123');
    }

    public function testRevokeConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentRevokedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher);

        $consent = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $this->consentRepository
            ->expects($this->once())
            ->method('updateConsentState')
            ->with($consent, null, ConsentStatus::REVOKED, 'user-456');

        $service->revokeConsent('consent-1', 'user-456');
    }

    public function testRevokeConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([]);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->revokeConsent('non-existent', 'user-123');
    }

    public function testGetHistory(): void
    {
        $service = $this->createService();

        $consent = new Consent('id-1', 'consent-1', ConsentScope::GLOBAL, new \DateTimeImmutable(), null);

        $history = [
            new ConsentStateHistoryItem(
                ConsentStatus::ACCEPTED,
                null,
                'user-123',
                new \DateTimeImmutable('2024-01-15 10:00:00')
            ),
            new ConsentStateHistoryItem(
                ConsentStatus::REQUESTED,
                null,
                'user-123',
                new \DateTimeImmutable('2024-01-10 10:00:00')
            ),
        ];

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects($this->once())
            ->method('getHistory')
            ->with('id-1', null)
            ->willReturn($history);

        $result = $service->getHistory('consent-1', 'user-123');

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
        $service = $this->createService();

        $consent = new Consent('id-2', 'consent-2', ConsentScope::ADMIN_USER, new \DateTimeImmutable(), null);

        $history = [
            new ConsentStateHistoryItem(
                ConsentStatus::REVOKED,
                'user-123',
                'user-123',
                new \DateTimeImmutable('2024-01-20 15:30:00')
            ),
            new ConsentStateHistoryItem(
                ConsentStatus::ACCEPTED,
                'user-123',
                'user-123',
                new \DateTimeImmutable('2024-01-15 10:00:00')
            ),
        ];

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects($this->once())
            ->method('getHistory')
            ->with('id-2', 'user-123')
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
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsents')
            ->willReturn([]);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->getHistory('non-existent', 'user-123');
    }

    private function createService(?EventDispatcher $eventDispatcher = null): ConsentService
    {
        return new ConsentService(
            $this->consentRepository,
            $eventDispatcher ?? new EventDispatcher()
        );
    }
}
