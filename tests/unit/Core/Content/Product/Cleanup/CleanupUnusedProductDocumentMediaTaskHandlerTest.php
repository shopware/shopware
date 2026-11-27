<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedProductDocumentMediaTaskHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedProductDocumentMediaTaskHandler::class)]
class CleanupUnusedProductDocumentMediaTaskHandlerTest extends TestCase
{
    public function testRunPurgesTheDocumentsFolderWithAOneDayGracePeriod(): void
    {
        $purger = $this->createMock(UnusedMediaPurger::class);
        $purger
            ->expects($this->once())
            ->method('deleteNotUsedMedia')
            ->with(50, null, 1, ProductDocumentDefinition::ENTITY_NAME);

        $handler = new CleanupUnusedProductDocumentMediaTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            static::createStub(LoggerInterface::class),
            $purger
        );

        $handler->run();
    }
}
