<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminCleanIndicesCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchAdminCleanIndicesCommand::class)]
class ElasticsearchAdminCleanIndicesCommandTest extends TestCase
{
    private IndicesNamespace&Stub $indices;

    private Client&Stub $client;

    protected function setUp(): void
    {
        $this->indices = static::createStub(IndicesNamespace::class);
        $this->client = static::createStub(Client::class);
        $this->client->method('indices')->willReturn($this->indices);
    }

    public function testExecuteWithAdminEsNotEnabled(): void
    {
        $detector = $this->createMock(AdminElasticsearchOutdatedIndexDetector::class);
        $detector->expects($this->never())->method('get');

        $commandTester = new CommandTester($this->createCommand($detector, false));
        $commandTester->execute([]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('Admin elasticsearch is not enabled', $commandTester->getDisplay());
    }

    public function testExecuteWithoutOutdatedIndices(): void
    {
        $commandTester = new CommandTester($this->createCommand($this->createDetector([])));
        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('No indices to be deleted.', $commandTester->getDisplay());
    }

    public function testExecuteAbortsWithoutConfirmation(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('delete');

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $command = new ElasticsearchAdminCleanIndicesCommand(
            $client,
            $this->createDetector(['sw-admin-product_1000']),
            $this->createHelper(true)
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Deletion aborted.', $commandTester->getDisplay());
    }

    public function testExecuteDeletesConfirmedIndices(): void
    {
        $deleted = [];

        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (array $arguments) use (&$deleted): array {
                $deleted[] = $arguments['index'];

                return [];
            });

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $command = new ElasticsearchAdminCleanIndicesCommand(
            $client,
            $this->createDetector(['sw-admin-product_1000', 'sw-admin-order_1000']),
            $this->createHelper(true)
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertSame(['sw-admin-product_1000', 'sw-admin-order_1000'], $deleted);
        static::assertStringContainsString('Indices deleted.', $commandTester->getDisplay());
    }

    private function createCommand(
        AdminElasticsearchOutdatedIndexDetector $detector,
        bool $enabled = true
    ): ElasticsearchAdminCleanIndicesCommand {
        return new ElasticsearchAdminCleanIndicesCommand($this->client, $detector, $this->createHelper($enabled));
    }

    /**
     * @param array<string> $outdated
     */
    private function createDetector(array $outdated): AdminElasticsearchOutdatedIndexDetector
    {
        $detector = static::createStub(AdminElasticsearchOutdatedIndexDetector::class);
        $detector->method('get')->willReturn($outdated);

        return $detector;
    }

    private function createHelper(bool $enabled): AdminElasticsearchHelper
    {
        $helper = static::createStub(AdminElasticsearchHelper::class);
        $helper->method('isEnabled')->willReturn($enabled);

        return $helper;
    }
}
