<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @internal
 */
#[Package('framework')]
final class StoreApiDTOResponseListener
{
    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof StoreApiDTOResponseInterface) {
            return;
        }

        $event->setResponse(new JsonResponse(get_object_vars($result)));
    }
}
