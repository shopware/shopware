<?php declare(strict_types=1);

namespace Shopware\Framework\EventListener;

use Shopware\Framework\HttpExceptionInterface;
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
