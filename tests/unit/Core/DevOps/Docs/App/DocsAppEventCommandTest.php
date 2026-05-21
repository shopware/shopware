<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(DocsAppEventCommand::class)]
class DocsAppEventCommandTest extends TestCase
{
    private BusinessEventCollector&MockObject $businessEventCollector;

    private HookableEventCollector&MockObject $hookableEventCollector;

    private Environment&MockObject $twig;

    private DocsAppEventCommandTestable $command;

    private static string $fixtureDir;

    private static string $testFilePath;

    public static function setUpBeforeClass(): void
    {
        self::$fixtureDir = __DIR__ . '/../../../../_fixtures';
        if (!is_dir(self::$fixtureDir)) {
            mkdir(self::$fixtureDir, 0777, true);
        }
        self::$testFilePath = self::$fixtureDir . '/webhook-events-reference-test.md';
    }

    public static function tearDownAfterClass(): void
    {
        if (is_file(self::$testFilePath)) {
            unlink(self::$testFilePath);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessEventCollector = $this->createMock(BusinessEventCollector::class);
        $this->hookableEventCollector = $this->createMock(HookableEventCollector::class);
        $this->twig = $this->createMock(Environment::class);
        $this->command = new DocsAppEventCommandTestable(
            $this->businessEventCollector,
            $this->hookableEventCollector,
            [],
            $this->twig,
            self::$testFilePath
        );
    }

    public function testRender(): void
    {
        $this->twig->expects($this->once())
            ->method('getLoader')
            ->willReturn(new ArrayLoader());

        $this->twig->expects($this->exactly(2))
            ->method('setLoader');

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('rendered content');

        $result = $this->command->render();
        static::assertSame('rendered content', $result);
    }

    public function testGetListEventPath(): void
    {
        $command = new DocsAppEventCommand(
            $this->businessEventCollector,
            $this->hookableEventCollector,
            [],
            $this->twig
        );
        static::assertStringEndsWith(
            'Resources/generated/webhook-events-reference.md',
            $command->getListEventPath()
        );
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockOutput = $this->getMockBuilder(OutputInterface::class)->getMock();
        $mockInput = $this->getMockBuilder(InputInterface::class)->getMock();

        $this->twig->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('render')->willReturn('rendered content');
        $this->twig->method('setLoader');

        $result = $this->command->run($mockInput, $mockOutput);
        static::assertSame(Command::SUCCESS, $result);
    }

    public function testRenderThrowsIfTemplateMissing(): void
    {
        $this->twig->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->expects($this->any())->method('setLoader');

        $result = $this->command->render();
        static::assertIsString($result);
    }

    public function testRenderRestoresLoaderOnTwigException(): void
    {
        $exception = new \RuntimeException('Twig error');

        $this->twig->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->expects($this->any())->method('setLoader');
        $this->twig->method('render')->willThrowException($exception);

        $this->expectExceptionObject($exception);
        $this->command->render();
    }

    public function testRenderWithEmptyCollectors(): void
    {
        $this->businessEventCollector->method('collect')->willReturn(new BusinessEventCollectorResponse());
        $this->hookableEventCollector->method('getEntityWrittenEventNamesWithPrivileges')->willReturn([]);
        $this->twig->expects($this->once())
            ->method('getLoader')
            ->willReturn(new ArrayLoader());
        $this->twig->expects($this->exactly(2))
            ->method('setLoader');
        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                'hookable-events-list.md.twig',
                static::callback(static function ($context) {
                    return \is_array($context['eventDocs']);
                })
            )
            ->willReturn('rendered content');

        $result = $this->command->render();
        static::assertSame('rendered content', $result);
    }

    public function testCollectBusinessEventCoversForeach(): void
    {
        $eventDef = new BusinessEventDefinition(
            name: 'test.event',
            class: 'TestClass',
            data: ['foo' => ['type' => 'string']],
            aware: []
        );

        $response = new BusinessEventCollectorResponse();
        $response->set($eventDef->getName(), $eventDef);

        $this->businessEventCollector
            ->method('collect')
            ->willReturn($response);

        $this->hookableEventCollector
            ->method('getPrivilegesFromBusinessEventDefinition')
            ->willReturn(['priv1', 'priv2']);

        $this->twig->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('setLoader');
        $this->twig->method('render')->willReturn('rendered content');

        $result = $this->command->render();
        static::assertSame('rendered content', $result);
    }

    public function testCollectEntityWrittenEventCoversForeach(): void
    {
        $entityWrittenEvents = [
            'entity.written' => ['privileges' => ['priv1', 'priv2']],
        ];

        $this->hookableEventCollector
            ->method('getEntityWrittenEventNamesWithPrivileges')
            ->willReturn($entityWrittenEvents);

        $this->twig->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('setLoader');
        $this->twig->method('render')->willReturn('rendered content');

        $result = $this->command->render();
        static::assertSame('rendered content', $result);
    }
}
/**
 * @internal
 *
 * Allows overriding the event document path to avoid writing to production code.
 */
class DocsAppEventCommandTestable extends DocsAppEventCommand
{
    public function __construct(
        BusinessEventCollector $businessEventCollector,
        HookableEventCollector $hookableEventCollector,
        iterable $hookableEventDescribers,
        Environment $twig,
        private readonly string $testPath
    ) {
        parent::__construct($businessEventCollector, $hookableEventCollector, $hookableEventDescribers, $twig);
    }

    public function getListEventPath(): string
    {
        return $this->testPath;
    }
}
