<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Permission\RemoteLogger;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(PermissionsService::class)]
class PermissionsServiceTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private RemoteLogger&MockObject $remoteConsentLogger;

    private PermissionsService $permissionsService;

    private Context $context;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->remoteConsentLogger = $this->createMock(RemoteLogger::class);
        $this->clock = new MockClock('2025-06-13 12:00:00');
        $this->permissionsService = new PermissionsService(
            $this->systemConfigService,
            $this->eventDispatcher,
            $this->remoteConsentLogger,
            $this->clock
        );

        $this->context = new Context(new AdminApiSource(Uuid::randomHex()));
    }

    public function testGrantPermissionsWithValidRevision(): void
    {
        $revision = '2025-06-13';

        $this->systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with('core.services.permissionsConsent', static::callback(static function ($value) use ($revision) {
                $decodedValue = json_decode($value, true);
                if (!\is_array($decodedValue) || !isset($decodedValue['revision'])) {
                    return false;
                }

                return $decodedValue['revision'] === $revision;
            }));

        $this->remoteConsentLogger
            ->expects($this->once())
            ->method('log');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (PermissionsGrantedEvent $event) use ($revision) {
                return $event->permissionsConsent->revision === $revision
                    && $event->context === $this->context;
            }));

        $this->permissionsService->grant($revision, $this->context);
    }

    public function testGrantPermissionsWithInvalidRevisionFormat(): void
    {
        $invalidRevision = 'invalid-date';

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsRevisionFormat($invalidRevision));

        $this->permissionsService->grant($invalidRevision, $this->context);
    }

    public function testGrantPermissionsWithIncorrectDateFormat(): void
    {
        $invalidRevision = '13-06-2025';

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsRevisionFormat($invalidRevision));

        $this->permissionsService->grant($invalidRevision, $this->context);
    }

    public function testRevokePermissions(): void
    {
        $consentJson = json_encode([
            'identifier' => 'test-identifier',
            'revision' => '2025-06-13T00:00:00+00:00',
            'consentingUserId' => 'test-user-id',
            'grantedAt' => '2025-06-13 12:00:00',
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn($consentJson);

        $this->systemConfigService
            ->expects($this->once())
            ->method('delete')
            ->with('core.services.permissionsConsent');

        $this->remoteConsentLogger
            ->expects($this->once())
            ->method('log');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (PermissionsRevokedEvent $event) {
                return $event->context === $this->context;
            }));

        $this->permissionsService->revoke($this->context);
    }

    public function testGrantPermissionsWithNonAdminApiSource(): void
    {
        $shopApiSource = new ShopApiSource('shop-id');
        $context = new Context($shopApiSource);

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsContext());

        $this->permissionsService->grant('2025-06-13', $context);
    }

    public function testGrantPermissionsWithSystemApiSource(): void
    {
        $systemSource = new SystemSource();
        $context = new Context($systemSource);

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsContext());

        $this->permissionsService->grant('2025-06-13', $context);
    }

    public function testGrantPermissionsWithAdminApiSourceButNoUserId(): void
    {
        $adminApiSource = new AdminApiSource(null);
        $context = new Context($adminApiSource);

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsContext());
        $this->permissionsService->grant('2025-06-13', $context);
    }

    public function testGrantPermissionsWithEmptyRevision(): void
    {
        $revision = '';

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');
        $this->expectExceptionObject(ServiceException::invalidPermissionsRevisionFormat($revision));
        $this->permissionsService->grant($revision, $this->context);
    }

    public function testRevokePermissionsWithEmptyConfig(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn('');

        // Expect that the delete method is called even if there is no existing consent
        $this->systemConfigService
            ->expects($this->once())
            ->method('delete')
            ->with('core.services.permissionsConsent');

        $this->remoteConsentLogger
            ->expects($this->never())
            ->method('log');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->permissionsService->revoke($this->context);
    }

    public function testMultipleGrantPermissionsCallsOverwritePrevious(): void
    {
        $revision1 = '2025-06-13';
        $revision2 = '2025-06-14';

        $matcher = $this->exactly(2);
        $this->systemConfigService
            ->expects($matcher)
            ->method('set')
            ->willReturnCallback(function (string $key, string $value) use ($matcher, $revision1, $revision2): void {
                static::assertSame('core.services.permissionsConsent', $key);

                $decodedValue = json_decode($value, true);
                static::assertIsArray($decodedValue);
                static::assertSame(
                    $this->clock->now()->format(\DateTimeInterface::ATOM),
                    $decodedValue['grantedAt']
                );
                static::assertSame(
                    $matcher->numberOfInvocations() === 1 ? $revision1 : $revision2,
                    $decodedValue['revision']
                );
            });

        $this->remoteConsentLogger
            ->expects($this->exactly(2))
            ->method('log');

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch');

        $this->permissionsService->grant($revision1, $this->context);
        $this->permissionsService->grant($revision2, $this->context);
    }

    public function testAreGrantedReturnsTrueWhenPermissionsExist(): void
    {
        $validConsentJson = json_encode([
            'identifier' => 'test-identifier',
            'revision' => '2025-06-13T00:00:00+00:00',
            'consentingUserId' => 'test-user-id',
            'grantedAt' => '2025-06-13 12:00:00',
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn($validConsentJson);

        $result = $this->permissionsService->areGranted();

        static::assertTrue($result);
    }

    public function testAreGrantedReturnsFalseWhenNoPermissionsExist(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn('');

        $result = $this->permissionsService->areGranted();

        static::assertFalse($result);
    }

    public function testAreGrantedReturnsFalseWhenPermissionsDataIsInvalid(): void
    {
        $invalidConsentJson = 'invalid-json-data';

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn($invalidConsentJson);

        $result = $this->permissionsService->areGranted();

        static::assertFalse($result);
    }

    public function testAreGrantedReturnsFalseWhenPermissionsDataIsMalformed(): void
    {
        $malformedConsentJson = json_encode([
            'identifier' => 'test-identifier',
            // Missing required fields: revision, consentingUserId, grantedAt
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.permissionsConsent')
            ->willReturn($malformedConsentJson);

        $result = $this->permissionsService->areGranted();

        static::assertFalse($result);
    }
}
