<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\PermissionsService;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(PermissionsService::class)]
class PermissionsServiceTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private PermissionsService $permissionsService;

    private Context $context;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->permissionsService = new PermissionsService(
            $this->systemConfigService,
            $this->eventDispatcher
        );
        $this->context = Context::createDefaultContext();
    }

    public function testGrantPermissionsWithValidRevision(): void
    {
        $revision = '2025-06-13';
        $expectedStorageFormat = (new \DateTimeImmutable($revision))->format(Defaults::STORAGE_DATE_FORMAT);

        $this->systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with('core.services.acceptedPermissionsRevision', $expectedStorageFormat);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (PermissionsGrantedEvent $event) use ($revision) {
                return $event->revision->format('Y-m-d') === $revision
                    && $event->context === $this->context;
            }));

        $this->permissionsService->grantPermissions($revision, $this->context);
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

        $this->permissionsService->grantPermissions($invalidRevision, $this->context);
    }

    public function testGrantPermissionsWithIncorrectDateFormat(): void
    {
        $invalidRevision = '13-06-2025'; // Wrong format

        $this->systemConfigService
            ->expects($this->never())
            ->method('set');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectExceptionObject(ServiceException::invalidPermissionsRevisionFormat($invalidRevision));

        $this->permissionsService->grantPermissions($invalidRevision, $this->context);
    }

    public function testRevokePermissions(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('delete')
            ->with('core.services.acceptedPermissionsRevision');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (PermissionsRevokedEvent $event) {
                return $event->context === $this->context;
            }));

        $this->permissionsService->revokePermissions($this->context);
    }

    public function testGetAcceptedPermissionsRevisionReturnsNull(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.acceptedPermissionsRevision')
            ->willReturn('');

        $result = $this->permissionsService->getAcceptedPermissionsRevision();

        static::assertNull($result);
    }

    public function testGetAcceptedPermissionsRevisionReturnsParsedDate(): void
    {
        $date = new \DateTimeImmutable('2025-06-13');
        $storageFormat = $date->format(Defaults::STORAGE_DATE_FORMAT);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.acceptedPermissionsRevision')
            ->willReturn($storageFormat);

        $result = $this->permissionsService->getAcceptedPermissionsRevision();

        static::assertInstanceOf(\DateTimeInterface::class, $result);
        static::assertSame($storageFormat, $result->format(Defaults::STORAGE_DATE_FORMAT));
    }

    public function testGetAcceptedPermissionsRevisionReturnsNullForInvalidDate(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with('core.services.acceptedPermissionsRevision')
            ->willReturn('invalid-date-format');

        $result = $this->permissionsService->getAcceptedPermissionsRevision();

        static::assertNull($result);
    }
}
