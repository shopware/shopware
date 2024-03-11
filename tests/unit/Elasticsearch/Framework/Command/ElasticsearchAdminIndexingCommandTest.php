<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package system-settings
 *
 * @internal
 */
#[CoversClass(ElasticsearchAdminIndexingCommand::class)]
class ElasticsearchAdminIndexingCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $registry = $this->getMockBuilder(AdminSearchRegistry::class)->disableOriginalConstructor()->getMock();

        $registry->expects(static::any())->method('iterate')->with(new AdminIndexingBehavior(true, [], ['promotion']));
        $commandTester = new CommandTester(new ElasticsearchAdminIndexingCommand($registry));
        $commandTester->execute(['--no-queue' => true, '--only' => 'promotion']);

        $commandTester->assertCommandIsSuccessful();
    }
}
