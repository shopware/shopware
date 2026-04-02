<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\Script\TriggerReferenceGeneratorCommand;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(TriggerReferenceGeneratorCommand::class)]
class TriggerReferenceGeneratorCommandTest extends TestCase
{
    private BusinessEventCollector&MockObject $collector;

    private Filesystem&MockObject $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = $this->createMock(BusinessEventCollector::class);
        $this->filesystem = $this->createMock(Filesystem::class);
    }

    public function testExecuteFailsWhenDescriptionsFileDoesNotExist(): void
    {
        $this->filesystem->method('exists')->willReturn(false);
        $this->collector->expects($this->never())->method('collect');

        $command = new TriggerReferenceGeneratorCommandTestable($this->collector, $this->filesystem, []);
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('Descriptions file is missing', $tester->getDisplay());
        static::assertStringContainsString('trigger-event-description.php', $tester->getDisplay());
    }

    public function testExecuteSucceedsAndWritesMarkdownFile(): void
    {
        $this->filesystem->method('exists')->willReturn(true);

        $response = new BusinessEventCollectorResponse();
        $response->set('test.event.one', new BusinessEventDefinition(
            name: 'test.event.one',
            class: \stdClass::class,
            data: [],
            aware: []
        ));
        $response->set('test.event.two', new BusinessEventDefinition(
            name: 'test.event.two',
            class: \stdClass::class,
            data: [],
            aware: []
        ));

        $this->collector->method('collect')->willReturn($response);

        $writtenContent = null;
        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->willReturnCallback(static function (string $path, string $content) use (&$writtenContent): void {
                $writtenContent = $content;
            });

        $command = new TriggerReferenceGeneratorCommandTestable(
            $this->collector,
            $this->filesystem,
            [
                'test.event.one' => 'First test event',
                'test.event.two' => 'Second test event',
            ]
        );
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertIsString($writtenContent);
        static::assertStringContainsString('# Trigger Events Reference', $writtenContent);
        static::assertStringContainsString('| Event | Description |', $writtenContent);
        static::assertStringContainsString('test.event.one', $writtenContent);
        static::assertStringContainsString('First test event', $writtenContent);
        static::assertStringContainsString('test.event.two', $writtenContent);
        static::assertStringContainsString('Second test event', $writtenContent);
        static::assertStringContainsString('Trigger reference generated', $tester->getDisplay());
    }

    public function testExecuteUsesEventClassAsDescriptionFallback(): void
    {
        $this->filesystem->method('exists')->willReturn(true);

        $response = new BusinessEventCollectorResponse();
        $response->set('no.description.event', new BusinessEventDefinition(
            name: 'no.description.event',
            class: \stdClass::class,
            data: [],
            aware: []
        ));

        $this->collector->method('collect')->willReturn($response);

        $writtenContent = null;
        $this->filesystem->method('dumpFile')
            ->willReturnCallback(static function (string $path, string $content) use (&$writtenContent): void {
                $writtenContent = $content;
            });

        $command = new TriggerReferenceGeneratorCommandTestable($this->collector, $this->filesystem, []);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertIsString($writtenContent);
        static::assertStringContainsString('no.description.event', $writtenContent);
        static::assertStringContainsString(\stdClass::class, $writtenContent);
    }

    public function testRealLoadDescriptionsAndOutputPathAreCovered(): void
    {
        $this->filesystem->method('exists')->willReturn(true);

        $response = new BusinessEventCollectorResponse();
        $this->collector->method('collect')->willReturn($response);

        $this->filesystem->expects($this->once())->method('dumpFile');

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testExecuteSortsRowsByEventName(): void
    {
        $this->filesystem->method('exists')->willReturn(true);

        $response = new BusinessEventCollectorResponse();
        $response->set('z.event', new BusinessEventDefinition(name: 'z.event', class: \stdClass::class, data: [], aware: []));
        $response->set('a.event', new BusinessEventDefinition(name: 'a.event', class: \stdClass::class, data: [], aware: []));

        $this->collector->method('collect')->willReturn($response);

        $writtenContent = null;
        $this->filesystem->method('dumpFile')
            ->willReturnCallback(static function (string $path, string $content) use (&$writtenContent): void {
                $writtenContent = $content;
            });

        $command = new TriggerReferenceGeneratorCommandTestable($this->collector, $this->filesystem, []);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertIsString($writtenContent);

        $positionA = strpos($writtenContent, 'a.event');
        $positionZ = strpos($writtenContent, 'z.event');
        static::assertNotFalse($positionA);
        static::assertNotFalse($positionZ);
        static::assertLessThan($positionZ, $positionA);
    }
}

/**
 * @internal
 *
 * Overrides description loading to avoid any filesystem access in unit tests.
 */
class TriggerReferenceGeneratorCommandTestable extends TriggerReferenceGeneratorCommand
{
    /**
     * @param array<string, string> $descriptions
     */
    public function __construct(
        BusinessEventCollector $collector,
        Filesystem $filesystem,
        private readonly array $descriptions,
    ) {
        parent::__construct($collector, $filesystem);
    }

    /**
     * @return array<string, string>
     */
    protected function loadDescriptions(): array
    {
        return $this->descriptions;
    }
}
