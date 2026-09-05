<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\ClusterNamespace;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminStatusCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchAdminStatusCommand::class)]
class ElasticsearchAdminStatusCommandTest extends TestCase
{
    public function testFailsWhenAdminElasticsearchIsDisabled(): void
    {
        $commandTester = new CommandTester($this->createCommand([], [], false));
        $commandTester->execute([]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('Admin elasticsearch is not enabled', $commandTester->getDisplay());
    }

    public function testWarnsWhenNoIndexWasBuiltYet(): void
    {
        $commandTester = new CommandTester($this->createCommand([], []));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('completed', $display);
        static::assertStringContainsString('No admin indices have been built yet', $display);
    }

    public function testReportsRunningIndexingPerEntity(): void
    {
        $commandTester = new CommandTester($this->createCommand(
            [
                ['entity' => 'order', 'index' => 'sw-admin-order-listing_2000', 'alias' => 'sw-admin-order-listing', 'doc_count' => '42'],
                ['entity' => 'promotion', 'index' => 'sw-admin-promotion-listing_1000', 'alias' => 'sw-admin-promotion-listing', 'doc_count' => '0'],
            ],
            ['sw-admin-promotion-listing' => ['sw-admin-promotion-listing_1000']]
        ));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('in progress', $display);
        static::assertStringContainsString('42', $display);
        static::assertStringContainsString('indexing', $display);
        static::assertStringContainsString('live', $display);
    }

    public function testMarksAFinishedIndexAsWaitingForItsAliasSwap(): void
    {
        $commandTester = new CommandTester($this->createCommand(
            [
                ['entity' => 'promotion', 'index' => 'sw-admin-promotion-listing_1000', 'alias' => 'sw-admin-promotion-listing', 'doc_count' => '0'],
                ['entity' => 'promotion', 'index' => 'sw-admin-promotion-listing_2000', 'alias' => 'sw-admin-promotion-listing', 'doc_count' => '0'],
            ],
            ['sw-admin-promotion-listing' => ['sw-admin-promotion-listing_1000']]
        ));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertStringContainsString('completed', $display);
        static::assertStringContainsString('live', $display);
        static::assertStringContainsString('waiting for alias swap', $display);
    }

    /**
     * @param list<array{entity: string, index: string, alias: string, doc_count: string}> $tasks
     * @param array<string, list<string>> $aliases
     */
    private function createCommand(array $tasks, array $aliases, bool $enabled = true): ElasticsearchAdminStatusCommand
    {
        $cluster = static::createStub(ClusterNamespace::class);
        $cluster->method('health')->willReturn(['status' => 'green', 'number_of_nodes' => 1]);

        $indices = static::createStub(IndicesNamespace::class);
        $indices->method('existsAlias')->willReturnCallback(
            static fn (array $arguments): bool => isset($aliases[$arguments['name']])
        );
        $indices->method('getAlias')->willReturnCallback(
            static fn (array $arguments): array => array_fill_keys($aliases[$arguments['name']] ?? [], ['aliases' => []])
        );

        $client = static::createStub(Client::class);
        $client->method('ping')->willReturn(true);
        $client->method('cluster')->willReturn($cluster);
        $client->method('indices')->willReturn($indices);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($tasks);

        return new ElasticsearchAdminStatusCommand(
            $client,
            $connection,
            new AdminElasticsearchHelper($enabled, false, 'sw-admin', 'test', true, new NullLogger())
        );
    }
}
