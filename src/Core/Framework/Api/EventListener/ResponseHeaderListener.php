<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
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
        $headersBag = $event->getResponse()->headers;
        foreach (self::HEADERS as $header) {
            if ($headersBag->has($header) || !$event->getRequest()->headers->has($header)) {
                continue;
            }

            $headersBag->set(
                $header,
                $event->getRequest()->headers->get($header),
                false
            );
        }
        if (!$headersBag->has(PlatformRequest::HEADER_FRAME_OPTIONS)) {
            $headersBag->set(
                PlatformRequest::HEADER_FRAME_OPTIONS,
                'deny',
                false
            );
        }
    }
}
