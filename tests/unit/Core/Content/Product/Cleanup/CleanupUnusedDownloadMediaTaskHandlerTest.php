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
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedDownloadMediaTaskHandler::class)]
class CleanupUnusedDownloadMediaTaskHandlerTest extends TestCase
{
    private MockObject&UnusedMediaPurger $purger;

    private CleanupUnusedDownloadMediaTaskHandler $handler;

    protected function setUp(): void
    {
        $this->purger = $this->createMock(UnusedMediaPurger::class);

        $this->handler = new CleanupUnusedDownloadMediaTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->purger
        );
    }

    public function testRunPurgesTheDownloadsFolderWithAOneDayGracePeriod(): void
    {
        $this->purger
            ->expects($this->once())
            ->method('deleteNotUsedMedia')
            ->with(50, null, 1, ProductDownloadDefinition::ENTITY_NAME);

        $this->handler->run();
    }
}
