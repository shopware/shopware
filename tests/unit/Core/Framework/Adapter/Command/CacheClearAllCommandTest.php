<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Test\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CacheClearAllCommand::class)]
class CacheClearAllCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $kernel = new TestKernel(
            'test',
            true,
            $this->createMock(KernelPluginLoader::class),
            'test',
            '',
            $this->createMock(Connection::class),
            'test'
        );

        $application = new Application($kernel);

        $command = new CacheClearAllCommand($this->createMock(CacheClearer::class));
        $command->setApplication($application);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
