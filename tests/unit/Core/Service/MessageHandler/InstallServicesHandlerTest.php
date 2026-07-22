<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\MessageHandler\InstallServicesHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallServicesHandler::class)]
class InstallServicesHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('reconcile')
            ->with(static::isInstanceOf(Context::class));

        $handler = new InstallServicesHandler($lifecycleManager);
        $handler->__invoke(new InstallServicesMessage());
    }
}
