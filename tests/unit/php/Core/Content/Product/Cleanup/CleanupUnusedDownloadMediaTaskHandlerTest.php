<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    private CleanupUnusedDownloadMediaTaskHandler $handler;

    protected function setUp(): void
    {
        $this->purger = $this->createMock(UnusedMediaPurger::class);

        $this->handler = new CleanupUnusedDownloadMediaTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->purger
        );
    }

    public function testGetHandledMessages(): void
    {
        static::assertEquals([CleanupUnusedDownloadMediaTask::class], $this->handler->getHandledMessages());
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
