<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class CacheClearAllCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testExecute(): void
    {
        $cacheClearer = self::getContainer()->get(CacheClearer::class);

        $command = new CacheClearAllCommand($cacheClearer);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
