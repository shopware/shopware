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
#[Package('framework')]
class ResponseHeaderListener implements EventSubscriberInterface
{
    /**
     * These Store API routes create or switch to a different context token and are allowed to expose
     * the new token to clients. Other routes may consume the request token but must not echo it back.
     */
    private const CONTEXT_TOKEN_RESPONSE_ROUTES = [
        'store-api.account.imitate-customer-login',
        'store-api.account.login',
        'store-api.account.logout',
        'store-api.account.register',
        'store-api.account.register.confirm',
        'store-api.context.gateway',
        'store-api.order',
    ];

    private const HEADERS = [
        PlatformRequest::HEADER_VERSION_ID,
        PlatformRequest::HEADER_LANGUAGE_ID,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $this->removeDisallowedContextTokenHeader($event);

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
    }

    private function removeDisallowedContextTokenHeader(ResponseEvent $event): void
    {
        $headersBag = $event->getResponse()->headers;
        if (!$headersBag->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (\is_string($route) && \in_array($route, self::CONTEXT_TOKEN_RESPONSE_ROUTES, true)) {
            return;
        }

        $headersBag->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);
    }
}
