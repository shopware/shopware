<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Message\LogPermissionToRegistryMessage;
use Shopware\Core\Service\MessageHandler\LogConsentToRegistryHandler;
use Shopware\Core\Service\Permission\ConsentState;
use Shopware\Core\Service\Permission\PermissionsConsent;
use Shopware\Core\Service\ServiceRegistry\PermissionLogger;

/**
 * @internal
 */
#[CoversClass(LogConsentToRegistryHandler::class)]
class LogConsentToRegistryHandlerTest extends TestCase
{
    private PermissionLogger&MockObject $permissionLogger;

    private LogConsentToRegistryHandler $handler;

    protected function setUp(): void
    {
        $this->permissionLogger = $this->createMock(PermissionLogger::class);
        $this->handler = new LogConsentToRegistryHandler($this->permissionLogger);
    }

    public function testHandlerCallOffloadsToPermissionLogger(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $message = new LogPermissionToRegistryMessage($consent, ConsentState::GRANTED);

        $this->permissionLogger
            ->expects($this->once())
            ->method('logSync')
            ->with($consent, ConsentState::GRANTED);

        $this->handler->__invoke($message);
    }
}
