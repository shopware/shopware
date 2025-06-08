<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class StoreApiResponseListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StructEncoder $encoder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly MediaUrlPlaceholderHandlerInterface $mediaUrlPlaceholderHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['encodeResponse', 10000],
        ];
    }

    public function encodeResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof StoreApiResponse) {
            return;
        }

        $this->dispatch($event);

        $includes = $event->getRequest()->get('includes', []);

        if (!\is_array($includes)) {
            $includes = explode(',', $includes);
        }

        $fields = new ResponseFields($includes);

        $encoded = $this->encoder->encode($response->getObject(), $fields);

        $jsonResponse = new JsonResponse(null, $response->getStatusCode(), $response->headers->all());
        $jsonResponse->setEncodingOptions(\JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES);
        $jsonResponse->setData($encoded);

        $content = $this->mediaUrlPlaceholderHandler->replace((string) $jsonResponse->getContent());

        $salesChannelContext = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if ($salesChannelContext instanceof SalesChannelContext) {
            $content = $this->seoUrlPlaceholderHandler->replace($content, '', $salesChannelContext);
        }

        $jsonResponse->setContent($content);

        $event->setResponse($jsonResponse);
    }

    /**
     * Equivalent to `\Shopware\Core\Framework\Routing\RouteEventSubscriber::render`
     */
    private function dispatch(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_route')) {
            return;
        }

        $name = $request->attributes->get('_route') . '.encode';
        $this->dispatcher->dispatch($event, $name);
    }
}
