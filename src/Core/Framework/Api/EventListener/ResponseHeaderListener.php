<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseHeaderListener implements EventSubscriberInterface
{
    private const HEADERS = [
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_LANGUAGE_ID,
        PlatformRequest::HEADER_CONTEXT_TOKEN,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        foreach (self::HEADERS as $header) {
            $event->getResponse()->headers->set(
                $header,
                $event->getRequest()->headers->get($header),
                false
            );
        }
        $event->getResponse()->headers->set(
            PlatformRequest::HEADER_FRAME_OPTIONS,
            'deny',
            false
        );
    }
}
