<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class ContextAwareCacheHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContextAwareCacheHeadersService $contextAwareCacheService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'store-api.scope.response' => ['onResponse', -1000],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        // Add context headers to the response
        $this->contextAwareCacheService->addContextHeaders($request, $response, $context);
    }
}
