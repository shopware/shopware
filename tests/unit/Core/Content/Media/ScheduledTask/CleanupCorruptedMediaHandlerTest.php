<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CleanupCorruptedMediaHandler::class)]
class CleanupCorruptedMediaHandlerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ScheduledTaskCollection>
     */
    private StaticEntityRepository $scheduledTaskRepository;

    private LoggerInterface&MockObject $logger;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->scheduledTaskRepository = new StaticEntityRepository([]);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testRunCleanupCorruptedMediaSuccessfully(): void
    {
        $data = [
            'id1' => ['primaryKey' => 'id1', 'data' => []],
            'id2' => ['primaryKey' => 'id2', 'data' => []],
        ];

        $ids = new IdSearchResult(2, $data, new Criteria(), Context::createDefaultContext());

        $this->mediaRepository = new StaticEntityRepository([$ids]);

        $handler = $this->createHandler();
        $handler->run();

        $deletes = $this->mediaRepository->deletes[0];
        static::assertIsArray($deletes);

        $deletedIds = array_column($deletes, 'id');
        static::assertCount(2, $deletedIds);

        static::assertSame('id1', $deletedIds[0]);
        static::assertSame('id2', $deletedIds[1]);
    }

    public function testRunCleansNothingUpIfNoCorruptedMediaExists(): void
    {
        $ids = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());

        $this->mediaRepository = new StaticEntityRepository([$ids]);

        $handler = $this->createHandler();
        $handler->run();

        static::assertEmpty($this->mediaRepository->deletes);
    }

    private function createHandler(): CleanupCorruptedMediaHandler
    {
        return new CleanupCorruptedMediaHandler($this->scheduledTaskRepository, $this->logger, $this->mediaRepository);
    }
}
