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
use Symfony\Component\Filesystem\Exception\IOException;
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
        $this->filesystem->method('readFile')->willThrowException(new IOException('File not found.'));
        $this->collector->expects($this->once())->method('collect')->willReturn(new BusinessEventCollectorResponse());

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('Descriptions file is missing or unreadable', $tester->getDisplay());
        static::assertStringContainsString('trigger-event-description.json', $tester->getDisplay());
    }

    public function testExecuteFailsWhenDescriptionsFileContainsInvalidJson(): void
    {
        $this->filesystem->method('readFile')->willReturn('"just a string"');

        $this->collector->expects($this->once())->method('collect')->willReturn(new BusinessEventCollectorResponse());

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('Failed to parse descriptions file', $tester->getDisplay());
    }

    public function testExecuteSucceedsAndWritesMarkdownFile(): void
    {
        $this->filesystem->method('readFile')->willReturn(json_encode([
            'test.event.one' => 'Triggers when an order enters status "Open"',
            'test.event.two' => 'Triggers when an order enters status "Cancelled"',
        ], \JSON_THROW_ON_ERROR));

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

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertIsString($writtenContent);
        static::assertStringContainsString('# Trigger Events Reference', $writtenContent);
        static::assertStringContainsString('| Event | Description |', $writtenContent);
        static::assertStringContainsString('test.event.one', $writtenContent);
        static::assertStringContainsString('Triggers when an order enters status "Open"', $writtenContent);
        static::assertStringContainsString('test.event.two', $writtenContent);
        static::assertStringContainsString('Triggers when an order enters status "Cancelled"', $writtenContent);
        static::assertStringContainsString('Trigger reference generated', $tester->getDisplay());
    }

    public function testExecuteUsesEventClassAsDescriptionFallback(): void
    {
        $this->filesystem->method('readFile')->willReturn('{}');

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

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertIsString($writtenContent);
        static::assertStringContainsString('no.description.event', $writtenContent);
        static::assertStringContainsString(\stdClass::class, $writtenContent);
    }

    public function testExecuteSortsRowsByEventName(): void
    {
        $this->filesystem->method('readFile')->willReturn('{}');

        $response = new BusinessEventCollectorResponse();
        $response->set('z.event', new BusinessEventDefinition(name: 'z.event', class: \stdClass::class, data: [], aware: []));
        $response->set('a.event', new BusinessEventDefinition(name: 'a.event', class: \stdClass::class, data: [], aware: []));

        $this->collector->method('collect')->willReturn($response);

        $writtenContent = null;
        $this->filesystem->method('dumpFile')
            ->willReturnCallback(static function (string $path, string $content) use (&$writtenContent): void {
                $writtenContent = $content;
            });

        $command = new TriggerReferenceGeneratorCommand($this->collector, $this->filesystem);
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
