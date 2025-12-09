<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentDTO;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\Consent\Storage\StorageInterface;
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

    private MockObject&StorageInterface $storage;

    protected function setUp(): void
    {
        $this->consentRepository = $this->createMock(ConsentRepository::class);
        $this->storage = $this->createMock(StorageInterface::class);
    }

    public function testList(): void
    {
        $service = $this->createService();

        $consent1 = new ConsentDTO('id-1', 'consent-1', 'test_storage', new \DateTimeImmutable(), null);
        $consent2 = new ConsentDTO('id-2', 'consent-2', 'test_storage', new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$consent1, $consent2]);

        $statusDto1 = new ConsentStateDTO('consent-1', 'user-123', ConsentState::ACCEPTED);
        $statusDto2 = new ConsentStateDTO('consent-2', 'user-123', ConsentState::REQUESTED);

        $this->storage
            ->expects($this->exactly(2))
            ->method('status')
            ->willReturnCallback(fn (string $name, string $userId) => match ($name) {
                'consent-1' => $statusDto1,
                'consent-2' => $statusDto2,
                default => null,
            });

        $result = $service->list('user-123');

        static::assertCount(2, $result);
        static::assertContains($statusDto1, $result);
        static::assertContains($statusDto2, $result);
    }

    public function testListCachesConsents(): void
    {
        $service = $this->createService();

        $consent = new ConsentDTO('id-1', 'consent-1', 'test_storage', new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$consent]);

        $this->storage
            ->expects($this->exactly(2))
            ->method('status')
            ->willReturn(new ConsentStateDTO('consent-1', 'user-123', ConsentState::ACCEPTED));

        $service->list('user-123');
        $service->list('user-123');
    }

    public function testGetConsentStatus(): void
    {
        $service = $this->createService();

        $consent = new ConsentDTO('id-1', 'test-consent', 'test_storage', new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$consent]);

        $statusDto = new ConsentStateDTO('test-consent', 'user-123', ConsentState::ACCEPTED);

        $this->storage
            ->expects($this->once())
            ->method('status')
            ->with('test-consent', 'user-123')
            ->willReturn($statusDto);

        $result = $service->getConsentStatus('test-consent', 'user-123');

        static::assertSame($statusDto, $result);
    }

    public function testGetConsentStatusThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
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

        $consent = new ConsentDTO('id-1', 'test-consent', 'test_storage', new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$consent]);

        $this->storage
            ->expects($this->once())
            ->method('accept')
            ->with('test-consent', 'user-123');

        $service->acceptConsent('test-consent', 'user-123');
    }

    public function testAcceptConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
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

        $consent = new ConsentDTO('id-1', 'test-consent', 'test_storage', new \DateTimeImmutable(), null);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$consent]);

        $this->storage
            ->expects($this->once())
            ->method('revoke')
            ->with('test-consent', 'user-456');

        $service->revokeConsent('test-consent', 'user-456');
    }

    public function testRevokeConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService();

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->revokeConsent('non-existent', 'user-123');
    }

    private function createService(?EventDispatcher $eventDispatcher = null): ConsentService
    {
        return new ConsentService(
            $this->consentRepository,
            ['test_storage' => $this->storage],
            $eventDispatcher ?? new EventDispatcher()
        );
    }
}
