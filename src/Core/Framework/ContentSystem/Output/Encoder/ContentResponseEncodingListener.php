<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Where a content response stops being a struct the framework encodes and becomes a body the module wrote.
 *
 * It does two things to every content response, and the selection is by response class: the four format
 * responses and the admin preview response share {@see AbstractContentRouteResponse}, while route scope and
 * route name discriminate neither (the generated routes share the store-api scope, and the preview route has
 * none of it).
 *
 * FIELD SELECTION IS REMOVED, not honoured. `includes` and `excludes` are unset in all three parameter bags,
 * so the framework builds an empty allow-all field set and neither top-level nor dotted-selector filtering can
 * reach a content body. All three bags because the parameter helper reads all three, and the intercepted
 * preview route is a POST whose parameters arrive in the request bag.
 *
 * THE FULL FORMAT IS REPLACED by a carrier bearing the module's own body. Status and headers are copied across
 * explicitly, because a store-api response holds its payload as a constructor-injected struct and has no
 * setter for it. The formats still assembled from the bridged page — skeleton, decomposed, data — keep passing
 * through the framework encoder untouched until their own encoders land.
 *
 * The priority sits strictly between the two neighbours that must keep their order around this: SEO-URL
 * enrichment above, the framework's store-api encoding below. Both take their priority from their own
 * `getSubscribedEvents()`, not from configuration. Running above the encoder is what makes the swap happen
 * before the route's `.encode` event, so an `.encode` listener observes the carrier.
 *
 * @internal
 */
#[Package('framework')]
class ContentResponseEncodingListener implements EventSubscriberInterface
{
    /**
     * Below `StoreApiSeoResolver` (11000) and above `StoreApiResponseListener` (10000).
     */
    private const PRIORITY = 10500;

    private const FIELD_SELECTION_PARAMETERS = ['includes', 'excludes'];

    public function __construct(private readonly ContentPageEncoder $pageEncoder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', self::PRIORITY],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof AbstractContentRouteResponse) {
            return;
        }

        $this->removeFieldSelection($event->getRequest());

        if (!$response instanceof ContentRouteResponse) {
            return;
        }

        $encoded = new StoreApiResponse($this->pageEncoder->encode($response->getRenderResult()));
        $encoded->setStatusCode($response->getStatusCode());
        $encoded->headers->replace($response->headers->all());

        $event->setResponse($encoded);
    }

    private function removeFieldSelection(Request $request): void
    {
        foreach (self::FIELD_SELECTION_PARAMETERS as $parameter) {
            $request->attributes->remove($parameter);
            $request->query->remove($parameter);
            $request->request->remove($parameter);
        }
    }
}
