<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Script\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Event\RenderStorefrontForScriptEvent;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Answers Core's {@see RenderStorefrontForScriptEvent} by rendering the
 * requested Twig view through {@see ScriptController}. This keeps the
 * `response.render()` script-service entry point in Core (so the documented
 * API surface stays put) while the actual Storefront rendering call lives
 * in the Storefront package.
 *
 * @internal
 */
#[Package('framework')]
class ScriptResponseRenderSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ScriptController $scriptController)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RenderStorefrontForScriptEvent::class => 'render',
        ];
    }

    public function render(RenderStorefrontForScriptEvent $event): void
    {
        $event->response = $this->scriptController->renderStorefrontForScript($event->view, $event->parameters);
    }
}
