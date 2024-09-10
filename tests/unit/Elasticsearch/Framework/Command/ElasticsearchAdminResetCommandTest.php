<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminResetCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ElasticsearchAdminResetCommand::class)]
class ElasticsearchAdminResetCommandTest extends TestCase
{
    private Connection $connection;

    private Client&MockObject $client;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->client = $this->createMock(Client::class);
    }

    public function testExecuteWithEsNotEnabled(): void
    {
        $searchHelper = $this->getMockBuilder(AdminElasticsearchHelper::class)->disableOriginalConstructor()->getMock();
        $searchHelper->expects(static::any())->method('getEnabled')->willReturn(false);
        $commandTester = new CommandTester(
            new ElasticsearchAdminResetCommand(
                $this->client,
                $this->connection,
                $this->createMock(IncrementGatewayRegistry::class),
                $searchHelper
            )
        );
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('Admin elasticsearch is not enabled', $message);
    }

    public function testExecuteWithInputNo(): void
    {
        $searchHelper = $this->getMockBuilder(AdminElasticsearchHelper::class)->disableOriginalConstructor()->getMock();
        $searchHelper->expects(static::any())->method('getEnabled')->willReturn(true);
        $commandTester = new CommandTester(
            new ElasticsearchAdminResetCommand(
                $this->client,
                $this->connection,
                $this->createMock(IncrementGatewayRegistry::class),
                $searchHelper
            )
        );
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('Are you sure you want to reset the Admin Elasticsearch indexing?', $message);
        static::assertStringContainsString('Canceled clearing indexing process', $message);
    }

    public function testExecute(): void
    {
        $searchHelper = $this->getMockBuilder(AdminElasticsearchHelper::class)->disableOriginalConstructor()->getMock();
        $searchHelper->expects(static::any())->method('getEnabled')->willReturn(true);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(static::once())->method('get')->willReturn([]);

        $this->client->method('indices')->willReturn($indices);

        $commandTester = new CommandTester(
            new ElasticsearchAdminResetCommand(
                $this->client,
                $this->connection,
                $this->createMock(IncrementGatewayRegistry::class),
                $searchHelper
            )
        );
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('Are you sure you want to reset the Admin Elasticsearch indexing?', $message);
        static::assertStringContainsString('Admin Elasticsearch indices deleted and queue cleared', $message);
    }
}
