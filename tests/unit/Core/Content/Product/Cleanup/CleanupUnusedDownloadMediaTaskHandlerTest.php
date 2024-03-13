<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler
 */
class CleanupUnusedDownloadMediaTaskHandlerTest extends TestCase
{
    private MockObject&UnusedMediaPurger $purger;

    private MockObject&Connection $connection;

    private CleanupUnusedDownloadMediaTaskHandler $handler;

    protected function setUp(): void
    {
        $this->purger = $this->createMock(UnusedMediaPurger::class);
        $this->connection = $this->createMock(Connection::class);

        $this->handler = new CleanupUnusedDownloadMediaTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->purger,
            $this->connection
        );
    }

    public function testGetHandledMessages(): void
    {
        static::assertEquals([CleanupUnusedDownloadMediaTask::class], $this->handler->getHandledMessages());
    }

    public function testDoesNotRunIfJsonOverlapNotAvailable(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT JSON_OVERLAPS(JSON_ARRAY(1), JSON_ARRAY(1));')
            ->willThrowException(new \Exception('Not available'));

        $this->purger
            ->expects(static::never())
            ->method('deleteNotUsedMedia');

        $this->handler->run();
    }

    public function testRun(): void
    {
        $this->purger
            ->expects(static::once())
            ->method('deleteNotUsedMedia')
            ->with(null, null, null, ProductDownloadDefinition::ENTITY_NAME);

        $this->handler->run();
    }
}
