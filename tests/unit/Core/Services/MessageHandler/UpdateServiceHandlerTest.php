<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\Message\UpdateServiceMessage;
use Shopware\Core\Services\MessageHandler\UpdateServiceHandler;
use Shopware\Core\Services\ScheduledTask\InstallServicesTask;
use Shopware\Core\Services\ServiceLifecycle;

/**
 * @internal
 */
#[CoversClass(InstallServicesTask::class)]
class UpdateServiceHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects(static::once())->method('update')->with('MyCoolService');

        $handler = new UpdateServiceHandler($lifecycle);
        $handler->__invoke(new UpdateServiceMessage('MyCoolService'));
    }
}
