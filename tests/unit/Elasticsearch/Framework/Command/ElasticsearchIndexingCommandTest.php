<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand
 */
class ElasticsearchIndexingCommandTest extends TestCase
{
    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testExecute(): void
    {
        $oldIndexer = $this->getMockBuilder(ElasticsearchIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects(static::never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testExecuteQueue(): void
    {
        $oldIndexer = $this->getMockBuilder(ElasticsearchIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects(static::once())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--no-queue' => true]);

        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testEsDisabled(): void
    {
        $oldIndexer = $this->getMockBuilder(ElasticsearchIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects(static::never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, false));
        $commandTester->execute(['--no-queue' => true], ['capture_stderr_separately' => true]);

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('[ERROR] Elasticsearch indexing is disabled', $output);
    }

    public function testExecuteOnly(): void
    {
        $oldIndexer = $this->getMockBuilder(ElasticsearchIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);
        $aliasHandler->expects(static::never())->method('run');

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true));
        $commandTester->execute(['--only' => 'product,category']);

        $commandTester->assertCommandIsSuccessful();
    }
}
