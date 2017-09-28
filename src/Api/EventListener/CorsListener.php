<?php declare(strict_types=1);

namespace Shopware\Api\EventListener;

use Shopware\Framework\Routing\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($event->getRequest()->attributes->get(Router::IS_API_REQUEST_ATTRIBUTE) === false) {
            return;
        }

        $method = $request->getRealMethod();

        if ($method === 'OPTIONS') {
            $response = new Response();
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($event->getRequest()->attributes->get(Router::IS_API_REQUEST_ATTRIBUTE) === false) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
    }
}
