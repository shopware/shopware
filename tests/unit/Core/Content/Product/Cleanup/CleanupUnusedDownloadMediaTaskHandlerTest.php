<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
#[CoversClass(CleanupUnusedDownloadMediaTaskHandler::class)]
class CleanupUnusedDownloadMediaTaskHandlerTest extends TestCase
{
    private MockObject&UnusedMediaPurger $purger;

    private CleanupUnusedDownloadMediaTaskHandler $handler;

    protected function setUp(): void
    {
        $this->purger = $this->createMock(UnusedMediaPurger::class);

        $this->handler = new CleanupUnusedDownloadMediaTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->purger
        );
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
