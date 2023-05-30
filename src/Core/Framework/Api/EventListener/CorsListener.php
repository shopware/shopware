<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class CorsListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $method = $event->getRequest()->getRealMethod();

        if ($method === 'OPTIONS') {
            $response = new Response();
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $corsHeaders = [
            'Content-Type',
            'Authorization',
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            PlatformRequest::HEADER_ACCESS_KEY,
            PlatformRequest::HEADER_LANGUAGE_ID,
            PlatformRequest::HEADER_VERSION_ID,
            PlatformRequest::HEADER_INHERITANCE,
            PlatformRequest::HEADER_FAIL_ON_ERROR,
            PlatformRequest::HEADER_INDEXING_BEHAVIOR,
            PlatformRequest::HEADER_SINGLE_OPERATION,
            PlatformRequest::HEADER_INCLUDE_SEO_URLS,
        ];

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
        $response->headers->set('Access-Control-Allow-Headers', implode(',', $corsHeaders));
        $response->headers->set('Access-Control-Expose-Headers', implode(',', $corsHeaders));
    }
}
