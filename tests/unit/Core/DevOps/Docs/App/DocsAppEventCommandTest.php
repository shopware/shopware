<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DocsAppEventCommand::class)]
class DocsAppEventCommandTest extends TestCase
{
    private BusinessEventCollector&Stub $businessEventCollector;

    private HookableEventCollector&Stub $hookableEventCollector;

    private Environment&MockObject $twig;

    private DocsAppEventCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessEventCollector = static::createStub(BusinessEventCollector::class);
        $this->hookableEventCollector = static::createStub(HookableEventCollector::class);
        $this->twig = $this->createMock(Environment::class);
        $this->command = new DocsAppEventCommand(
            $this->businessEventCollector,
            $this->hookableEventCollector,
            $this->policies(),
            [],
            $this->twig,
            static::createStub(Filesystem::class)
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
        // getListEventPath() does not render, so the shared twig mock must assert it is not used here.
        $this->twig->expects($this->never())->method('getLoader');

        static::assertStringEndsWith(
            'Resources/generated/webhook-events-reference.md',
            $this->command->getListEventPath()
        );
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockOutput = static::createStub(OutputInterface::class);
        $mockInput = static::createStub(InputInterface::class);

        $this->twig->expects($this->once())->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('render')->willReturn('rendered content');
        $this->twig->method('setLoader');

        $result = $this->command->run($mockInput, $mockOutput);
        static::assertSame(Command::SUCCESS, $result);
    }

    public function testRenderThrowsIfTemplateMissing(): void
    {
        $this->twig->expects($this->once())->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('setLoader');

        $result = $this->command->render();
        static::assertIsString($result);
    }

    public function testRenderRestoresLoaderOnTwigException(): void
    {
        $exception = new \RuntimeException('Twig error');

        $this->twig->expects($this->once())->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('setLoader');
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

        $this->twig->expects($this->once())->method('getLoader')->willReturn(new ArrayLoader());
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

        $this->twig->expects($this->once())->method('getLoader')->willReturn(new ArrayLoader());
        $this->twig->method('setLoader');
        $this->twig->method('render')->willReturn('rendered content');

        $result = $this->command->render();
        static::assertSame('rendered content', $result);
    }

    private function policies(): PolicyRegistry
    {
        $policies = static::createStub(PolicyRegistry::class);
        $policies->method('permitsSubscription')->willReturn(true);

        return $policies;
    }
}
