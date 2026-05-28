<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Script\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Event\RenderStorefrontForScriptEvent;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Framework\Script\Subscriber\ScriptResponseRenderSubscriber;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptResponseRenderSubscriber::class)]
class ScriptResponseRenderSubscriberTest extends TestCase
{
    #[TestDox('Subscribes to RenderStorefrontForScriptEvent')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [RenderStorefrontForScriptEvent::class => 'render'],
            ScriptResponseRenderSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('render() delegates to ScriptController::renderStorefrontForScript and sets the response on the event')]
    public function testRenderDelegatesToScriptControllerAndAssignsResponse(): void
    {
        $rendered = new Response('rendered storefront html', Response::HTTP_OK);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderStorefrontForScript')
            ->with('@Storefront/detail.html.twig', ['page' => 'data'])
            ->willReturn($rendered);

        $subscriber = new ScriptResponseRenderSubscriber($scriptController);

        $event = new RenderStorefrontForScriptEvent('@Storefront/detail.html.twig', ['page' => 'data'], null);
        $subscriber->render($event);

        static::assertSame($rendered, $event->response);
    }
}
