<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\MultilingualEsIndexer;
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
        $newIndexer = $this->getMockBuilder(MultilingualEsIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, true, $newIndexer));
        $commandTester->execute(['--no-queue' => true]);

        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testEsDisabled(): void
    {
        $oldIndexer = $this->getMockBuilder(ElasticsearchIndexer::class)->disableOriginalConstructor()->getMock();
        $newIndexer = $this->getMockBuilder(MultilingualEsIndexer::class)->disableOriginalConstructor()->getMock();

        $bus = $this->createMock(MessageBusInterface::class);
        $aliasHandler = $this->createMock(CreateAliasTaskHandler::class);

        $commandTester = new CommandTester(new ElasticsearchIndexingCommand($oldIndexer, $bus, $aliasHandler, false, $newIndexer));
        $commandTester->execute(['--no-queue' => true], ['capture_stderr_separately' => true]);

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('[ERROR] Elasticsearch indexing is disabled', $output);
    }
}
