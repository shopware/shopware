<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

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
        $entityDispatchService->expects($this->once())
            ->method('dispatchCollectEntityDataMessage');

        /** @var StaticEntityRepository<ScheduledTaskCollection> */
        $repository = new StaticEntityRepository([], new ScheduledTaskDefinition());

        $taskHandler = new CollectEntityDataTaskHandler(
            $repository,
            $this->createMock(LoggerInterface::class),
            $entityDispatchService
        );

        $taskHandler->run();
    }
}
