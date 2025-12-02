<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(DocsAppEventCommand::class)]
class DocsAppEventCommandTest extends TestCase
{
    public function testRender(): void
    {
        $businessEventCollector = $this->createMock(BusinessEventCollector::class);
        $hookableEventCollector = $this->createMock(HookableEventCollector::class);
        $twig = $this->createMock(Environment::class);

        $command = new DocsAppEventCommand($businessEventCollector, $hookableEventCollector, $twig);

        $twig->expects($this->once())
            ->method('getLoader')
            ->willReturn(new ArrayLoader());

        $twig->expects($this->exactly(2))
            ->method('setLoader');

        $twig->expects($this->once())
            ->method('render')
            ->willReturn('rendered content');

        $result = $command->render();

        static::assertSame('rendered content', $result);
    }
}
