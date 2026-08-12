<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @internal
 */
#[Package('framework')]
final class DTOResponseListener
{
    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof AbstractResponse) {
            return;
        }

        $data = get_object_vars($result);

        $extensions = $result->getExtensions();
        if ($extensions !== []) {
            $data['extensions'] = $extensions;
        }

        $event->setResponse(new JsonResponse($data));
    }
}
