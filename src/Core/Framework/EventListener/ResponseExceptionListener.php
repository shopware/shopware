<?php declare(strict_types=1);

namespace Shopware\Core\Framework\EventListener;

use Shopware\Core\Framework\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ResponseExceptionListener
{
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        throw $exception->getHttpException();
    }
}
