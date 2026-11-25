<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemPreviewToolbarSubscriber implements EventSubscriberInterface
{
    private const PREVIEW_ROUTE = 'frontend.content-system.preview';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -129],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->getRequest()->attributes->get('_route') !== self::PREVIEW_ROUTE) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || $content === '') {
            return;
        }

        $content = preg_replace(
            '/<!-- START of Symfony Web Debug Toolbar -->.*<!-- END of Symfony Web Debug Toolbar -->/sU',
            '',
            $content
        );

        if (\is_string($content)) {
            $response->setContent($content);
        }
    }
}
