<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchUpdateMappingCommand;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ElasticsearchUpdateMappingCommand::class)]
class ElasticsearchUpdateMappingCommandTest extends TestCase
{
    public function testUpdate(): void
    {
        $updater = $this->createMock(IndexMappingUpdater::class);
        $updater
            ->expects(static::once())
            ->method('update');

        $command = new ElasticsearchUpdateMappingCommand(
            $updater
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
    }
}
