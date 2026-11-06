<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Encoder;

use Shopware\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Where a content response stops being a struct the framework encodes and becomes a body the module wrote.
 *
 * It encodes, and nothing else. The selection is by response class: the four format responses share
 * {@see AbstractContentRouteResponse}, while route scope and route name discriminate neither.
 *
 * FIELD SELECTION IS REFUSED, not filtered here. A request carrying `includes` or `excludes` never reaches a
 * response at all: {@see ContentRoute::load()} rejects it with a 400 at request admission, so this listener
 * touches no parameter bag and every content body it produces is unfiltered by construction.
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
     * {@see ContentSkeletonRouteResponse} and only it.
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
}
