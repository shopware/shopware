<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDocumentMediaTaskHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedDocumentMediaTaskHandler::class)]
class CleanupUnusedDocumentMediaTaskHandlerTest extends TestCase
{
    public function testRunPurgesTheDocumentsFolderWithAOneDayGracePeriod(): void
    {
        $purger = $this->createMock(UnusedMediaPurger::class);
        $purger
            ->expects($this->once())
            ->method('deleteNotUsedMedia')
            ->with(50, null, 1, ProductDocumentDefinition::ENTITY_NAME);

        $handler = new CleanupUnusedDocumentMediaTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            static::createStub(LoggerInterface::class),
            $purger
        );

        $handler->run();
    }
}
