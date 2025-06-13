<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\PermissionsService;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
class PermissionsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PermissionsService $permissionsService;

    private SystemConfigService $systemConfigService;

    private TraceableEventDispatcher $eventDispatcher;

    private Context $context;

    protected function setUp(): void
    {
        $this->permissionsService = $this->getContainer()->get(PermissionsService::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->eventDispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->delete('core.services.acceptedPermissionsRevision');
    }

    public function testGrantPermissionsIntegration(): void
    {
        $revision = '2025-06-13';

        $this->permissionsService->grantPermissions($revision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        $expectedFormat = (new \DateTimeImmutable($revision))->format(Defaults::STORAGE_DATE_FORMAT);
        static::assertSame($expectedFormat, $storedRevision);
        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsGrantedEvents = array_filter($calledListeners, function ($listener) {
            return $listener['event'] === PermissionsGrantedEvent::class;
        });

        static::assertNotEmpty($permissionsGrantedEvents, 'PermissionsGrantedEvent should have been dispatched');
        $retrievedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertInstanceOf(\DateTimeInterface::class, $retrievedRevision);
        static::assertSame($revision, $retrievedRevision->format('Y-m-d'));
    }

    public function testRevokePermissionsIntegration(): void
    {
        $revision = '2025-06-13';
        $this->permissionsService->grantPermissions($revision, $this->context);
        static::assertNotNull($this->permissionsService->getAcceptedPermissionsRevision());
        $this->permissionsService->revokePermissions($this->context);
        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertSame('', $storedRevision);
        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsRevokedEvents = array_filter($calledListeners, function ($listener) {
            return $listener['event'] === PermissionsRevokedEvent::class;
        });
        static::assertNotEmpty($permissionsRevokedEvents, 'PermissionsRevokedEvent should have been dispatched');
        static::assertNull($this->permissionsService->getAcceptedPermissionsRevision());
    }

    public function testGrantPermissionsWithInvalidRevisionIntegration(): void
    {
        $invalidRevision = 'invalid-date';

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('The provided permissions revision "invalid-date" is not in the correct format Y-m-d.');
        $this->permissionsService->grantPermissions($invalidRevision, $this->context);
        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertSame('', $storedRevision);
    }

    public function testMultipleGrantPermissionsCallsOverridesPrevious(): void
    {
        $firstRevision = '2025-06-13';
        $secondRevision = '2025-06-14';
        $this->permissionsService->grantPermissions($firstRevision, $this->context);
        $retrievedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertInstanceOf(\DateTimeInterface::class, $retrievedRevision);
        static::assertSame($firstRevision, $retrievedRevision->format('Y-m-d'));
        $this->permissionsService->grantPermissions($secondRevision, $this->context);
        $retrievedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertInstanceOf(\DateTimeInterface::class, $retrievedRevision);
        static::assertSame($secondRevision, $retrievedRevision->format('Y-m-d'));
    }
}
