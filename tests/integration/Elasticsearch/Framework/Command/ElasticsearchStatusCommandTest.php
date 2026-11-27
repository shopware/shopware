<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\ClusterNamespace;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchStatusCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchStatusCommandTest extends TestCase
{
    private Connection $connection;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        // Any attempt to reconstruct progress from product fields must fail.
        $this->connection->executeStatement('CREATE TEMPORARY TABLE product (id BINARY(16) PRIMARY KEY)');
        $this->connection->beginTransaction();
        $this->connection->executeStatement('DELETE FROM elasticsearch_index_task');

        $cluster = static::createStub(ClusterNamespace::class);
        $cluster->method('health')->willReturn(['status' => 'green', 'number_of_nodes' => 1]);
        $indices = static::createStub(IndicesNamespace::class);
        $indices->method('getAlias')->willReturn(['product_1' => []]);
        $client = static::createStub(Client::class);
        $client->method('ping')->willReturn(true);
        $client->method('cluster')->willReturn($cluster);
        $client->method('indices')->willReturn($indices);

        $this->tester = new CommandTester(new ElasticsearchStatusCommand($client, $this->connection));
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TEMPORARY TABLE product');
    }

    public function testCompletedWithoutTaskDoesNotReadProducts(): void
    {
        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        static::assertStringContainsString('completed', $this->tester->getDisplay());
    }

    #[DataProvider('taskProvider')]
    public function testStatusUsesStoredTaskProgress(int $remaining, ?int $total, string $expected): void
    {
        $this->connection->insert('elasticsearch_index_task', [
            'id' => Uuid::randomBytes(),
            '`entity`' => 'product',
            '`index`' => 'product_1',
            '`alias`' => 'product',
            'doc_count' => $remaining,
            'total_count' => $total,
        ]);

        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        static::assertStringContainsString($expected, $this->tester->getDisplay());
    }

    /**
     * @return iterable<string, array{int, int|null, string}>
     */
    public static function taskProvider(): iterable
    {
        yield 'completed task' => [0, 100, 'completed'];
        yield 'stored total determines progress' => [25, 100, '75/100'];
        yield 'existing task without initial total' => [25, null, 'Remaining items: 25'];
        yield 'empty initial total with new work' => [25, 0, 'Remaining items: 25'];
        yield 'remaining exceeds initial total' => [125, 100, '0/100'];
    }
}
