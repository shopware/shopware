<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminUpdateMappingCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminUpdateMappingCommand
 */
class ElasticsearchAdminUpdateMappingCommandTest extends TestCase
{
    public function testUpdate(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry
            ->expects(static::once())
            ->method('updateMappings');

        $command = new ElasticsearchAdminUpdateMappingCommand($registry);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Updated mapping for admin indices', $commandTester->getDisplay());
    }
}
