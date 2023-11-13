<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\UsageData\ScheduledTask\CheckIntegrationChangedTaskHandler;
use Shopware\Core\System\UsageData\Services\IntegrationChangedService;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\ScheduledTask\CheckIntegrationChangedTaskHandler
 */
class CheckIntegrationAppUrlChangedTaskHandlerTest extends TestCase
{
    public function testItChecksAndHandlesIntegrationAppUrlChanged(): void
    {
        $integrationAppUrlChangedService = $this->createMock(IntegrationChangedService::class);
        $integrationAppUrlChangedService->expects(static::once())
            ->method('checkAndHandleIntegrationChanged');

        $taskHandler = new CheckIntegrationChangedTaskHandler(
            $this->createMock(EntityRepository::class),
            $integrationAppUrlChangedService,
        );

        $taskHandler->run();
    }
}
