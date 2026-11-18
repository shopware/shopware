<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexingDto;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexingCommand::class)]
class ElasticsearchIndexingCommandTest extends TestCase
{
    private ElasticsearchIndexer&MockObject $indexer;

    private CreateAliasTaskHandler&MockObject $aliasHandler;

    private CollectingMessageBus $bus;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->indexer = $this->createMock(ElasticsearchIndexer::class);
        $this->aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $this->bus = new CollectingMessageBus();
        $this->tester = new CommandTester(new ElasticsearchIndexingCommand($this->indexer, $this->bus, $this->aliasHandler, true));
    }

    public function testQueuesEveryBatch(): void
    {
        $first = $this->createMessage('first-product');
        $last = $this->createMessage('last-product');
        $this->indexer->expects($this->once())->method('iterateMessages')->willReturn((static function () use ($first, $last): \Generator {
            yield $first;
            yield $last;
        })());
        $this->aliasHandler->expects($this->never())->method('run');

        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        static::assertSame([$first, $last], array_map(static fn ($envelope) => $envelope->getMessage(), $this->bus->getMessages()));
    }

    public function testEarlierBatchesAreQueuedWhenALaterReadFails(): void
    {
        $first = $this->createMessage('first-product');
        $failure = new \RuntimeException('Catalog read failed');
        $this->indexer->expects($this->once())->method('iterateMessages')->willReturn((static function () use ($first, $failure): \Generator {
            yield $first;
            throw $failure;
        })());
        $this->aliasHandler->expects($this->never())->method('run');

        $this->expectExceptionObject($failure);

        try {
            $this->tester->execute([]);
        } finally {
            static::assertCount(1, $this->bus->getMessages());
            static::assertSame($first, $this->bus->getMessages()[0]->getMessage());
        }
    }

    public function testNoQueueProcessesTheBatchAndCreatesTheAlias(): void
    {
        $message = $this->createMessage('product');
        $this->indexer->method('iterateMessages')->willReturn((static function () use ($message): \Generator {
            yield $message;
        })());
        $this->indexer->expects($this->once())->method('__invoke')->with($message);
        $this->aliasHandler->expects($this->once())->method('run');

        $this->tester->execute(['--no-queue' => true]);

        $this->tester->assertCommandIsSuccessful();
        static::assertSame([], $this->bus->getMessages());
    }

    public function testEmptyIndexReportsNoMessages(): void
    {
        $this->indexer->expects($this->once())->method('iterateMessages');
        $this->aliasHandler->expects($this->never())->method('run');

        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        static::assertStringContainsString('No messages found for indexing.', $this->tester->getDisplay());
        static::assertSame([], $this->bus->getMessages());
    }

    public function testEsDisabled(): void
    {
        $this->aliasHandler->expects($this->never())->method('run');
        $this->indexer->expects($this->never())->method('iterateMessages');
        $tester = new CommandTester(new ElasticsearchIndexingCommand($this->indexer, $this->bus, $this->aliasHandler, false));

        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('Elasticsearch indexing is disabled', $tester->getDisplay());
    }

    public function testOnlySelectsTheRequestedEntities(): void
    {
        $this->aliasHandler->expects($this->never())->method('run');
        $this->indexer->expects($this->once())->method('iterateMessages')->with(['product', 'category']);

        $this->tester->execute(['--only' => 'product,category']);

        $this->tester->assertCommandIsSuccessful();
    }

    private function createMessage(string $id): ElasticsearchIndexingMessage
    {
        return new ElasticsearchIndexingMessage(new IndexingDto([$id], 'product-index', 'product'), null, Context::createDefaultContext());
    }
}
