<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final class DTOResponseListener
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly MediaUrlPlaceholderHandlerInterface $mediaUrlPlaceholderHandler,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof AbstractResponse) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.encode');
            $result = $event->getControllerResult();
            if ($event->hasResponse() || !$result instanceof AbstractResponse) {
                return;
            }
        }

        $json = $this->serializer->serialize(
            $result,
            'json',
            [JsonEncode::OPTIONS => \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES]
        );

        $content = $this->mediaUrlPlaceholderHandler->replace($json);
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if ($salesChannelContext instanceof SalesChannelContext) {
            $content = $this->seoUrlPlaceholderHandler->replace($content, '', $salesChannelContext);
        }

        $response = new JsonResponse($content, $result->getStatusCode(), $result->getHeaders(), json: true);
        foreach ($result->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event->setResponse($response);
    }
}
