<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexingDto;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchIndexingCommand::class)]
class ElasticsearchIndexingCommandTest extends TestCase
{
    #[DataProvider('executionModes')]
    public function testProcessesBatchesBeforeTheScanFinishes(bool $noQueue): void
    {
        $indexer = $this->createPartialMock(ElasticsearchIndexer::class, ['iterate', '__invoke']);
        $trace = [];
        $batch = 0;
        $indexer->method('iterate')->willReturnCallback(static function () use (&$trace, &$batch): ?ElasticsearchIndexingMessage {
            $trace[] = 'read';

            return ++$batch <= 3 ? new ElasticsearchIndexingMessage(
                new IndexingDto([(string) $batch], 'product-index', 'product'),
                null,
                Context::createDefaultContext(),
            ) : null;
        });
        $process = static function (ElasticsearchIndexingMessage $message) use (&$trace): Envelope {
            $trace[] = $message->isLastMessage() ? 'last' : 'process';

            return new Envelope($message);
        };
        $indexer->expects($noQueue ? $this->exactly(3) : $this->never())->method('__invoke')->willReturnCallback($process);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($noQueue ? $this->never() : $this->exactly(3))->method('dispatch')->willReturnCallback($process);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($noQueue ? $this->once() : $this->never())->method('run');

        $tester = new CommandTester(new ElasticsearchIndexingCommand($indexer, $bus, $aliasHandler, true));
        $tester->execute(['--no-queue' => $noQueue]);

        $tester->assertCommandIsSuccessful();
        static::assertSame(['read', 'read', 'process', 'read', 'process', 'read', 'last'], $trace);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function executionModes(): iterable
    {
        yield 'queued' => [false];
        yield 'synchronous' => [true];
    }

    public function testExecute(): void
    {
        $oldIndexer = $this->createPartialMock(ElasticsearchIndexer::class, ['iterate', '__invoke']);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteQueue(): void
    {
        $oldIndexer = $this->createPartialMock(ElasticsearchIndexer::class, ['iterate', '__invoke']);

        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([], 'product', 'product'),
            null,
            Context::createDefaultContext(),
            false
        );

        static::assertFalse($message->isLastMessage());
        $oldIndexer->method('iterate')->willReturnOnConsecutiveCalls(
            $message,
            null
        );

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->once())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--no-queue' => true]);

        static::assertTrue($message->isLastMessage());
        $commandTester->assertCommandIsSuccessful();
    }

    public function testEsDisabled(): void
    {
        $oldIndexer = $this->createPartialMock(ElasticsearchIndexer::class, ['iterate', '__invoke']);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, false));
        $commandTester->execute(['--no-queue' => true], ['capture_stderr_separately' => true]);

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('[ERROR] Elasticsearch indexing is disabled', $output);
    }

    public function testExecuteOnly(): void
    {
        $oldIndexer = $this->createPartialMock(ElasticsearchIndexer::class, ['iterate', '__invoke']);
        $oldIndexer->expects($this->once())->method('iterate')->with(null, ['product', 'category'])->willReturn(null);

        $bus = static::createStub(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects($this->never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--only' => 'product,category']);

        $commandTester->assertCommandIsSuccessful();
    }
}
