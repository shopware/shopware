<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('core')]
class JsonRequestTransformerListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 128],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->getContent() && mb_stripos($event->getRequest()->headers->get('Content-Type', ''), 'application/json') === 0) {
            $data = json_decode($event->getRequest()->getContent(), true);

            if (json_last_error() !== \JSON_ERROR_NONE) {
                throw new BadRequestHttpException('The JSON payload is malformed.');
            }

            $event->getRequest()->request->replace(\is_array($data) ? $data : []);
        }
    }
}
