<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataTaskHandler::class)]
class CollectEntityDataTaskHandlerTest extends TestCase
{
    public function testItStartsCollectingData(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::once())
            ->method('dispatchCollectEntityDataMessage');

        $taskHandler = new CollectEntityDataTaskHandler(
            new StaticEntityRepository([], new ScheduledTaskDefinition()),
            $entityDispatchService
        );

        $taskHandler->run();
    }
}
