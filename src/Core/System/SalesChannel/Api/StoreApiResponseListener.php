<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class StoreApiResponseListener implements EventSubscriberInterface
{
    /**
     * @var StructEncoder
     */
    private $encoder;

    public function __construct(StructEncoder $encoder)
    {
        $this->encoder = $encoder;
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

        $version = $event->getRequest()->attributes->getInt('version');

        $encoded = $this->encoder->encode($response->getObject(), $version, $fields);

        $event->setResponse(new JsonResponse($encoded, $response->getStatusCode(), $response->headers->all()));
    }
}
