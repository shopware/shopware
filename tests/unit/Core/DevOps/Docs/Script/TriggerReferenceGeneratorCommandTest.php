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
    private static string $fixtureDir;

    private static string $descriptionsPath;

    private static string $outputPath;

    private BusinessEventCollector&MockObject $collector;

    public static function setUpBeforeClass(): void
    {
        self::$fixtureDir = __DIR__ . '/../../../../_fixtures';
        if (!is_dir(self::$fixtureDir)) {
            mkdir(self::$fixtureDir, 0777, true);
        }
        self::$descriptionsPath = self::$fixtureDir . '/trigger-event-description-test.php';
        self::$outputPath = self::$fixtureDir . '/trigger-events-reference-test.md';
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$descriptionsPath, self::$outputPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([self::$descriptionsPath, self::$outputPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->collector = $this->createMock(BusinessEventCollector::class);
    }

    public function testExecuteFailsWhenDescriptionsFileDoesNotExist(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')
            ->with(self::$descriptionsPath)
            ->willReturn(false);

        $this->collector->expects($this->never())->method('collect');

        $command = new TriggerReferenceGeneratorCommandTestable(
            $this->collector,
            $filesystem,
            self::$descriptionsPath,
            self::$outputPath
        );
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
        static::assertStringContainsString('Descriptions file is missing', $tester->getDisplay());
        static::assertStringContainsString('trigger-event-description.php', $tester->getDisplay());
    }

    public function testExecuteSucceedsAndWritesMarkdownFile(): void
    {
        file_put_contents(self::$descriptionsPath, <<<'PHP'
<?php declare(strict_types=1);
return [
    'test.event.one' => 'First test event',
    'test.event.two' => 'Second test event',
];
PHP);

        $response = new BusinessEventCollectorResponse();
        $response->set('test.event.one', new BusinessEventDefinition('test.event.one', \stdClass::class, [], []));
        $response->set('test.event.two', new BusinessEventDefinition('test.event.two', \stdClass::class, [], []));

        $this->collector->method('collect')->willReturn($response);

        $command = new TriggerReferenceGeneratorCommandTestable(
            $this->collector,
            new Filesystem(),
            self::$descriptionsPath,
            self::$outputPath
        );
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertFileExists(self::$outputPath);

        $content = file_get_contents(self::$outputPath);
        static::assertStringContainsString('# Trigger Events Reference', $content);
        static::assertStringContainsString('| Event | Description |', $content);
        static::assertStringContainsString('test.event.one', $content);
        static::assertStringContainsString('First test event', $content);
        static::assertStringContainsString('test.event.two', $content);
        static::assertStringContainsString('Second test event', $content);
        static::assertStringContainsString('Trigger reference generated', $tester->getDisplay());
    }
}

/**
 * @internal
 *
 * Allows overriding description and output paths to avoid writing to production paths.
 */
class TriggerReferenceGeneratorCommandTestable extends TriggerReferenceGeneratorCommand
{
    public function __construct(
        BusinessEventCollector $collector,
        Filesystem $filesystem,
        private readonly string $eventDescriptionsPath,
        private readonly string $outputPath,
    ) {
        parent::__construct($collector, $filesystem);
    }

    protected function getEventDescriptionsPath(): string
    {
        return $this->eventDescriptionsPath;
    }

    protected function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
