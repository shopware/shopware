<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\ClusterNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchStatusCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchStatusCommand::class)]
class ElasticsearchStatusCommandTest extends TestCase
{
    public function testThrowsWhenServerIsNotReachable(): void
    {
        $client = static::createStub(Client::class);
        $client->method('ping')->willReturn(false);

        $commandTester = new CommandTester(
            new ElasticsearchStatusCommand($client, static::createStub(Connection::class))
        );

        $this->expectExceptionObject(ElasticsearchException::serverNotAvailable());

        $commandTester->execute([]);
    }

    public function testReportsCompletedWithoutPendingTasks(): void
    {
        $commandTester = new CommandTester($this->createCommand([]));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Cluster Status', $display);
        static::assertStringContainsString('completed', $display);
        static::assertStringNotContainsString('Remaining documents', $display);
    }

    public function testReportsEveryIndexedEntityNotJustProduct(): void
    {
        $commandTester = new CommandTester($this->createCommand([
            ['entity' => 'category', 'index' => 'sw_category_2000', 'alias' => 'sw_category', 'doc_count' => '0'],
            ['entity' => 'product', 'index' => 'sw_product_2000', 'alias' => 'sw_product', 'doc_count' => '120'],
        ]));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('in progress', $display);

        static::assertStringContainsString('category', $display);
        static::assertStringContainsString('sw_category_2000', $display);
        static::assertStringContainsString('waiting for alias swap', $display);

        static::assertStringContainsString('product', $display);
        static::assertStringContainsString('sw_product_2000', $display);
        static::assertStringContainsString('120', $display);
        static::assertStringContainsString('indexing', $display);
    }

    public function testNegativeRemainingCountsAreReportedAsZero(): void
    {
        $commandTester = new CommandTester($this->createCommand([
            ['entity' => 'product', 'index' => 'sw_product_2000', 'alias' => 'sw_product', 'doc_count' => '-5'],
        ]));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertStringContainsString('waiting for alias swap', $display);
        static::assertStringNotContainsString('-5', $display);
    }

    /**
     * @param list<array{entity: string, index: string, alias: string, doc_count: string}> $tasks
     */
    private function createCommand(array $tasks): ElasticsearchStatusCommand
    {
        $cluster = static::createStub(ClusterNamespace::class);
        $cluster->method('health')->willReturn(['status' => 'green', 'number_of_nodes' => 1]);

        $client = static::createStub(Client::class);
        $client->method('ping')->willReturn(true);
        $client->method('cluster')->willReturn($cluster);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($tasks);

        return new ElasticsearchStatusCommand($client, $connection);
    }
}
