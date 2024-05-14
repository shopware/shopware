<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Maintenance\Staging\Command\SystemSetupStagingCommand;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(SystemSetupStagingCommand::class)]
class SystemSetupStagingCommandTest extends TestCase
{
    public function testCancelPrompt(): void
    {
        $command = new SystemSetupStagingCommand(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class)
        );

        $tester = new CommandTester($command);

        $tester->setInputs(['no']);
        $tester->execute([]);
        static::assertEquals(Command::FAILURE, $tester->getStatusCode());
    }

    public function testRun(): void
    {
        $configService = new StaticSystemConfigService();
        $eventDispatcher = new CollectingEventDispatcher();

        $command = new SystemSetupStagingCommand(
            $eventDispatcher,
            $configService
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        static::assertTrue($configService->get('core.staging'));
        static::assertCount(1, $eventDispatcher->getEvents());

        $event = $eventDispatcher->getEvents()[0];

        static::assertInstanceOf(SetupStagingEvent::class, $event);
    }
}
