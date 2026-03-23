<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Component\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Component\Command\PublishComponentsCommand;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(PublishComponentsCommand::class)]
class PublishComponentsCommandTest extends TestCase
{
    public function testExecuteDelegatesToComponentPublisherForAllBundlesByDefault(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $publisher->expects($this->once())->method('publishAll');
        $publisher->method('readComponentManifest')->willReturn([
            'Sw:Button' => ['js' => '/components/Sw/Button.js'],
            'Sw:Card' => ['js' => '/components/Sw/Card.js'],
        ]);

        $tester = new CommandTester(new PublishComponentsCommand($publisher));
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Published 2 component entries', $tester->getDisplay());
    }

    public function testExecutePublishesSingleBundleWhenBundleOptionIsProvided(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $publisher->expects($this->once())
            ->method('publishBundleByName')
            ->with('MyBundle')
            ->willReturn(true);
        $publisher->expects($this->never())->method('publishAll');
        $publisher->method('readComponentManifest')->willReturn([
            'MyBundle:Sw:Button' => ['js' => '/components/MyBundle/Sw/Button.js'],
        ]);

        $tester = new CommandTester(new PublishComponentsCommand($publisher));
        $exitCode = $tester->execute(['--bundle' => 'MyBundle']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Published bundle "MyBundle"', $tester->getDisplay());
    }

    public function testExecuteFailsForUnknownBundleOption(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $publisher->expects($this->once())
            ->method('publishBundleByName')
            ->with('UnknownBundle')
            ->willReturn(null);
        $publisher->expects($this->never())->method('publishAll');
        $publisher->expects($this->never())->method('readComponentManifest');

        $tester = new CommandTester(new PublishComponentsCommand($publisher));
        $exitCode = $tester->execute(['--bundle' => 'UnknownBundle']);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('was not found in var/plugins.json', $tester->getDisplay());
    }

    public function testExecuteReportsNoteWhenNoEntriesWerePublished(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $publisher->method('readComponentManifest')->willReturn([]);

        $tester = new CommandTester(new PublishComponentsCommand($publisher));
        $tester->execute([]);

        static::assertStringContainsString('No component entries found', $tester->getDisplay());
    }

    public function testExecuteHandlesSingularEntryCountLabel(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $publisher->method('readComponentManifest')->willReturn([
            'Sw:Button' => ['js' => '/components/Sw/Button.js'],
        ]);

        $tester = new CommandTester(new PublishComponentsCommand($publisher));
        $tester->execute([]);

        static::assertStringContainsString('Published 1 component entry', $tester->getDisplay());
    }

    public function testCommandDefinesBundleOption(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $command = new PublishComponentsCommand($publisher);

        static::assertTrue($command->getDefinition()->hasOption('bundle'));
        static::assertFalse($command->getDefinition()->hasOption('all'));
    }

    public function testCommandName(): void
    {
        $publisher = $this->createMock(ComponentPublisher::class);
        $command = new PublishComponentsCommand($publisher);

        static::assertSame('storefront:publish-components', $command->getName());
    }
}
