<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ScheduledTask\UpdateProductStreamMappingTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(UpdateProductStreamMappingTaskHandler::class)]
class UpdateProductStreamMappingTaskHandlerTest extends TestCase
{
    public function testRunTouchesStreamsAndDispatchesIndexingMessages(): void
    {
        $streamIds = ['stream-until', 'stream-since'];

        /** @var StaticEntityRepository<ProductStreamCollection> $productStreamRepository */
        $productStreamRepository = new StaticEntityRepository([$streamIds]);

        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $handler = new UpdateProductStreamMappingTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $productStreamRepository,
            $messageBus,
        );

        $handler->run();

        static::assertSame(
            [[['id' => 'stream-until'], ['id' => 'stream-since']]],
            $productStreamRepository->updates,
            'expected scheduled task to touch stream rows so cache invalidation subscribers fire',
        );

        static::assertCount(2, $dispatched);
        foreach ($dispatched as $index => $message) {
            static::assertInstanceOf(ProductStreamMappingIndexingMessage::class, $message);
            static::assertSame($streamIds[$index], $message->getData());
            static::assertSame(ProductStreamUpdater::INDEXER_NAME, $message->getIndexer());
        }
    }

    public function testRunDoesNothingWhenNoStreamsMatch(): void
    {
        /** @var StaticEntityRepository<ProductStreamCollection> $productStreamRepository */
        $productStreamRepository = new StaticEntityRepository([[]]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = new UpdateProductStreamMappingTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $productStreamRepository,
            $messageBus,
        );

        $handler->run();

        static::assertSame([], $productStreamRepository->updates);
    }
}
