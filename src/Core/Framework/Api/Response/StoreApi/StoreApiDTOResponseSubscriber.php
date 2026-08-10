<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
final class StoreApiDTOResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onView', 1000],
        ];
    }

    public function onView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof StoreApiDTOResponseInterface) {
            return;
        }

        $event->setResponse(new JsonResponse(get_object_vars($result)));
    }
}
