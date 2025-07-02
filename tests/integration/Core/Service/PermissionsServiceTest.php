<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\Permission\PermissionsService;
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

        $this->permissionsService->grant($revision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertNotEmpty($storedRevision);

        // Verify the stored data contains the expected revision
        $decodedData = json_decode($storedRevision, true);
        static::assertIsArray($decodedData);
        static::assertArrayHasKey('revision', $decodedData);

        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($revision, $storedDate->format('Y-m-d'));

        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsGrantedEvents = array_filter($calledListeners, function ($listener) {
            return $listener['event'] === PermissionsGrantedEvent::class;
        });

        static::assertNotEmpty($permissionsGrantedEvents, 'PermissionsGrantedEvent should have been dispatched');
    }

    public function testRevokePermissionsIntegration(): void
    {
        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        // Verify permissions were granted
        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertNotEmpty($storedRevision);

        $this->permissionsService->revoke($this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertSame('', $storedRevision);

        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsRevokedEvents = array_filter($calledListeners, function ($listener) {
            return $listener['event'] === PermissionsRevokedEvent::class;
        });

        static::assertNotEmpty($permissionsRevokedEvents, 'PermissionsRevokedEvent should have been dispatched');
    }

    public function testGrantPermissionsWithInvalidRevisionIntegration(): void
    {
        $invalidRevision = 'invalid-date';

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('The provided permissions revision "invalid-date" is not in the correct format Y-m-d.');
        $this->permissionsService->grant($invalidRevision, $this->context);
        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        static::assertSame('', $storedRevision);
    }

    public function testMultipleGrantPermissionsCallsOverridesPrevious(): void
    {
        $firstRevision = '2025-06-13';
        $secondRevision = '2025-06-14';

        $this->permissionsService->grant($firstRevision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        $decodedData = json_decode($storedRevision, true);
        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($firstRevision, $storedDate->format('Y-m-d'));

        $this->permissionsService->grant($secondRevision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.acceptedPermissionsRevision');
        $decodedData = json_decode($storedRevision, true);
        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($secondRevision, $storedDate->format('Y-m-d'));
    }

    public function testAreGrantedReturnsTrueAfterGrantingPermissions(): void
    {
        static::assertFalse($this->permissionsService->areGranted());
        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        static::assertTrue($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseAfterRevokingPermissions(): void
    {
        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        static::assertTrue($this->permissionsService->areGranted());
        $this->permissionsService->revoke($this->context);
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWhenNoPermissionsExist(): void
    {
        $this->systemConfigService->delete('core.services.acceptedPermissionsRevision');
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWithCorruptedPermissionsData(): void
    {
        $this->systemConfigService->set('core.services.acceptedPermissionsRevision', 'invalid-json-data');
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWithIncompletePermissionsData(): void
    {
        $incompleteData = json_encode([
            'identifier' => 'test-identifier',
        ]);
        $this->systemConfigService->set('core.services.acceptedPermissionsRevision', $incompleteData);
        static::assertFalse($this->permissionsService->areGranted());
    }
}
