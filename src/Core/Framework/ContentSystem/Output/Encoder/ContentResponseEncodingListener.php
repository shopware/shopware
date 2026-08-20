<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
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
 * THREE FORMATS ARE REPLACED by a carrier bearing the module's own body — full, decomposed and data — each
 * through its own encoder. Status and headers are copied across explicitly, because a store-api response holds
 * its payload as a constructor-injected struct and has no setter for it. The skeleton format is deliberately
 * not among them: it is homogeneous, carries no entity payloads, and so keeps passing through the framework
 * encoder as a plain struct.
 *
 * The encoder is chosen by an explicit `match` over three concrete collaborators rather than through a common
 * interface. That keeps it greppable from here which encoder produced which format's body; an interface would
 * put a `->encode()` call in its place and hide exactly that.
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

    public function __construct(
        private readonly ContentPageEncoder $pageEncoder,
        private readonly ContentDecomposedPageEncoder $decomposedPageEncoder,
        private readonly ContentDataPageEncoder $dataPageEncoder,
    ) {
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

        $body = $this->encode($response);

        if ($body === null) {
            return;
        }

        $encoded = new StoreApiResponse($body);
        $encoded->setStatusCode($response->getStatusCode());
        $encoded->headers->replace($response->headers->all());

        $event->setResponse($encoded);
    }

    /**
     * A null says the response keeps its struct, which today is
     * {@see \Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse} and only it. The
     * preview response is not a case here: it is built by the full format's factory, so it arrives as a
     * {@see ContentRouteResponse} and is encoded as one.
     */
    private function encode(AbstractContentRouteResponse $response): ?EncodedContentPage
    {
        return match (true) {
            $response instanceof ContentRouteResponse => $this->pageEncoder->encode($response->getRenderResult()),
            $response instanceof ContentDecomposedRouteResponse => $this->decomposedPageEncoder->encode($response->getRenderResult()),
            $response instanceof ContentDataRouteResponse => $this->dataPageEncoder->encode($response->getRenderResult()),
            default => null,
        };
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
