<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class StoreApiResponseListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly StructEncoder $encoder)
    {
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

        $fields = new ResponseFields(
            $event->getRequest()->get('includes', [])
        );

        $encoded = $this->encoder->encode($response->getObject(), $fields);

        $event->setResponse(new JsonResponse($encoded, $response->getStatusCode(), $response->headers->all()));
    }
}
