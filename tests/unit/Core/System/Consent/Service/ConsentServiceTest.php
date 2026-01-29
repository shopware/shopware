<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentScope\AdminUser;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
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
            'consent-1' => ConsentScope\System::NAME,
            'consent-2' => AdminUser::NAME,
        ]);

        $record1 = new ConsentStateRecord('consent-1', 'system', ConsentStatus::ACCEPTED, 'user-123', '2026-01-26 00:00:00');
        $record2 = new ConsentStateRecord('consent-2', 'user-123', ConsentStatus::UNSET, 'user-123', '2026-01-26 00:00:00');
        $record3 = new ConsentStateRecord('consent-2', 'user-456', ConsentStatus::ACCEPTED, 'user-456', '2026-01-26 00:00:00');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$record1, $record2, $record3]);

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $result = $service->list($context);

        static::assertCount(2, $result);
        static::assertSame('consent-1', $result['consent-1']->name);
        static::assertSame(ConsentStatus::ACCEPTED, $result['consent-1']->status);
        static::assertSame('consent-2', $result['consent-2']->name);
        static::assertSame(ConsentStatus::UNSET, $result['consent-2']->status);
    }

    public function testListCachesConsents(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope\System::NAME,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $service->list($context);
        $service->list($context);
    }

    public function testGetConsentStatusThrowsExceptionWhenNoIdentifierGivenForAdminScope(): void
    {
        self::expectExceptionObject(ConsentException::cannotResolveScope(AdminUser::NAME));

        $service = $this->createService(null, [
            'consent-1' => AdminUser::NAME,
        ]);

        $context = Context::createDefaultContext();

        $service->getConsentState('consent-1', $context);
    }

    public function testGetConsentStatus(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope\System::NAME,
        ]);

        $record = new ConsentStateRecord('consent-1', ConsentScope\System::NAME, ConsentStatus::ACCEPTED, 'user-123', '2026-01-26 00:00:00');

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([$record]);

        $context = Context::createDefaultContext();

        $result = $service->getConsentState('consent-1', $context);

        static::assertSame('consent-1', $result->name);
        static::assertSame(ConsentScope\System::NAME, $result->scopeName);
        static::assertSame(ConsentScope\System::NAME, $result->identifier);
        static::assertSame(ConsentStatus::ACCEPTED, $result->status);
        static::assertSame('user-123', $result->actor);
    }

    public function testGetConsentStatusReturnsRequestedStateByDefault(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope\System::NAME,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([]);

        $context = Context::createDefaultContext();

        $result = $service->getConsentState('consent-1', $context);

        static::assertSame('consent-1', $result->name);
        static::assertSame(ConsentStatus::UNSET, $result->status);
        static::assertSame(ConsentScope\System::NAME, $result->identifier);
        static::assertNull($result->actor);
    }

    public function testGetConsentStatusThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $service->getConsentState('non-existent', Context::createDefaultContext());
    }

    public function testAcceptConsentIsNoopWhenConsentAlreadyAccepted(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope\System::NAME,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([new ConsentStateRecord('consent-1', 'system', ConsentStatus::ACCEPTED, 'user-123', '2026-01-26 00:00:00')]);

        $this->consentRepository
            ->expects($this->never())
            ->method('updateConsentState');

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $service->acceptConsent('consent-1', $context);
    }

    public function testAcceptConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentAcceptedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher, [
            'consent-1' => ConsentScope\System::NAME,
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
                'system',
                ConsentStatus::ACCEPTED,
                'user-123'
            )
            ->willReturn(new ConsentState('consent-1', 'system', 'system', ConsentStatus::ACCEPTED, 'user-123', '2026-01-26 00:00:00'));

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $updatedState = $service->acceptConsent('consent-1', $context);

        static::assertEquals(
            new ConsentState('consent-1', 'system', 'system', ConsentStatus::ACCEPTED, 'user-123', '2026-01-26 00:00:00'),
            $updatedState
        );
    }

    public function testAcceptConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $service->acceptConsent('non-existent', $context);
    }

    public function testRevokeConsentIsNoopWhenConsentAlreadyRevoked(): void
    {
        $service = $this->createService(null, [
            'consent-1' => ConsentScope\System::NAME,
        ]);

        $this->consentRepository
            ->expects($this->once())
            ->method('fetchAllConsentStates')
            ->willReturn([new ConsentStateRecord('consent-1', 'system', ConsentStatus::REVOKED, 'user-123', '2026-01-26 00:00:00')]);

        $this->consentRepository
            ->expects($this->never())
            ->method('updateConsentState');

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $service->revokeConsent('consent-1', $context);
    }

    public function testRevokeConsent(): void
    {
        $eventDispatcher = new AssertingEventDispatcher($this, [
            ConsentRevokedEvent::class => 1,
        ]);

        $service = $this->createService($eventDispatcher, [
            'consent-1' => ConsentScope\System::NAME,
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
                'system',
                ConsentStatus::REVOKED,
                'user-456'
            )
            ->willReturn(new ConsentState('consent-1', 'system', 'system', ConsentStatus::REVOKED, 'user-456', '2026-01-26 00:00:00'));

        $source = new AdminApiSource('user-456');
        $context = Context::createDefaultContext($source);

        $updatedState = $service->revokeConsent('consent-1', $context);

        static::assertEquals(
            new ConsentState('consent-1', 'system', 'system', ConsentStatus::REVOKED, 'user-456', '2026-01-26 00:00:00'),
            $updatedState
        );
    }

    public function testRevokeConsentThrowsExceptionWhenConsentNotFound(): void
    {
        $service = $this->createService(null, []);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent with name "non-existent" not found.');

        $source = new AdminApiSource('user-123');
        $context = Context::createDefaultContext($source);

        $service->revokeConsent('non-existent', $context);
    }

    /**
     * @param array<string, string> $consents
     */
    private function createService(?EventDispatcher $eventDispatcher = null, array $consents = []): ConsentService
    {
        $definitions = [];
        foreach ($consents as $name => $scope) {
            $definitions[] = new class($name, $scope) implements ConsentDefinition {
                public function __construct(
                    private readonly string $name,
                    private readonly string $scope
                ) {
                }

                public function getName(): string
                {
                    return $this->name;
                }

                public function getSince(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable();
                }

                public function getScopeName(): string
                {
                    return $this->scope;
                }
            };
        }

        $scopes = [
            new ConsentScope\System(),
            new AdminUser(),
        ];

        return new ConsentService($scopes, $definitions, $this->consentRepository, $eventDispatcher ?? new EventDispatcher());
    }
}
